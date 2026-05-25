#!/usr/bin/env python3
"""
DumbCapital Content Bot — Smart Curation Edition
github.com/jgman1/dumbcapital

How it works:
  1. Fetches all RSS feeds
  2. Filters to only stories not seen in previous runs (uses seen_cache.json)
  3. Sends ALL new stories to Claude for scoring/ranking by satirical potential
  4. Picks the highest-ranked unique story per section
  5. Writes the articles and saves posts
  6. Updates the seen cache so nothing is repeated next run

Cost: ~$0.05-0.15/week (Claude Haiku)
"""

import os, json, re, time, html, hashlib
try:
    from json_repair import repair_json
except ImportError:
    repair_json = None
import urllib.request
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path

# ── CONFIG ────────────────────────────────────────────────────
ANTHROPIC_API_KEY = os.environ["ANTHROPIC_API_KEY"]
POSTS_DIR    = Path("posts")
CACHE_FILE   = Path("posts/seen_cache.json")   # persists in the posts/ folder

# ── FREE RSS FEEDS ────────────────────────────────────────────
RSS_FEEDS = [
    "https://techcrunch.com/category/venture/feed/",
    "https://feeds.feedburner.com/TechCrunch",
    "https://feeds.content.dowjones.io/public/rss/mw-topstories",
    "https://feeds.reuters.com/reuters/businessNews",
    "https://www.axios.com/feeds/feed.rss",
    "https://financialpost.com/feed",
    "https://business.financialpost.com/feed",
    "https://www.theglobeandmail.com/rss/article/rss.xml",
]

# ── SECTIONS ──────────────────────────────────────────────────
SECTIONS = [
    {
        "key":     "vc",
        "label":   "VC Deals",
        "kickers": ["VC Nonsense", "Money Well Burned", "Series WTF", "Burn Rate Report"],
        "focus":   "venture capital funding rounds only — Series A, B, C, D raises, VC fund launches, "
                   "pre-revenue startups raising large rounds, seed rounds, venture debt. "
                   "Must involve a VC firm investing in a startup. "
                   "NOT for M&A deals, NOT for PE buyouts, NOT for public companies, NOT for opinion/commentary.",
    },
    {
        "key":     "ma",
        "label":   "M&A Morgue",
        "kickers": ["Deal of the Week", "M&A Morgue", "Synergy Watch", "Merger Theatre"],
        "focus":   "mergers and acquisitions only — one company buying another, corporate takeovers, "
                   "acqui-hires, strategic acquisitions, failed or collapsed deals, integration disasters. "
                   "Must involve an actual announced or completed acquisition. "
                   "NOT for VC funding rounds, NOT for PE buyouts (those go in PE Corner), NOT for IPOs.",
    },
    {
        "key":     "pe",
        "label":   "PE Corner",
        "kickers": ["PE Corner", "Value Destruction Watch", "Leverage Report", "Carried Interest"],
        "focus":   "private equity only — leveraged buyouts (LBOs), PE firm acquisitions, "
                   "roll-up strategies, portfolio company layoffs or restructuring, PE fund returns, "
                   "secondaries market, PE-backed company distress or bankruptcy. "
                   "Must involve a named private equity firm. "
                   "NOT for VC deals, NOT for strategic M&A between operating companies.",
    },
    {
        "key":     "unicorn",
        "label":   "Unicorn Watch",
        "kickers": ["Unicorn Watch", "Billion Dollar Club", "Valuation Theatre", "Imaginary Billions"],
        "focus":   "unicorn and mega-valuation stories only — startups valued at $1B+ achieving or losing unicorn status, "
                   "down rounds at previously high valuations, IPO disappointments vs private valuations, "
                   "valuation markdowns by mutual funds or investors, unicorn failures or shutdowns. "
                   "Must be about a STARTUP (not a public company, not a hedge fund, not a trading firm) "
                   "and must center on its VALUATION being absurd, inflated, or collapsing. "
                   "Jane Street, Citadel, hedge funds, trading firms do NOT belong here — they are not startups.",
    },
    {
        "key":     "opinion",
        "label":   "Opinion",
        "kickers": ["Hot Take", "The Verdict", "Unpopular Opinion", "Editorial"],
        "focus":   "opinion, analysis, and commentary — broader trends in VC or M&A, "
                   "criticism of industry culture or practices, stories about established firms or public figures "
                   "that don't fit neatly into a deal category, market commentary, think-pieces. "
                   "Use this section when a story is interesting and satirically rich but doesn't involve "
                   "a specific funding round, acquisition, PE buyout, or unicorn valuation. "
                   "Jane Street, Citadel, established hedge funds, and trading firms belong here if covered.",
    },
]


# ── SEEN CACHE ────────────────────────────────────────────────
def load_seen_cache() -> set:
    """Load the set of story hashes we've already processed."""
    POSTS_DIR.mkdir(exist_ok=True)
    if CACHE_FILE.exists():
        try:
            data = json.loads(CACHE_FILE.read_text())
            return set(data.get("seen", []))
        except Exception:
            pass
    return set()

def save_seen_cache(seen: set) -> None:
    """Save updated seen cache. Keep last 2000 hashes to prevent bloat."""
    hashes = list(seen)[-2000:]
    CACHE_FILE.write_text(json.dumps({"seen": hashes}, indent=2))


# ── FETCH ALL RSS ─────────────────────────────────────────────
def fetch_rss(url: str) -> list[dict]:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "DumbCapital/1.0"})
        with urllib.request.urlopen(req, timeout=12) as r:
            raw = r.read()
        root = ET.fromstring(raw)
        items, ns = [], {"a": "http://www.w3.org/2005/Atom"}
        for item in root.findall(".//item"):
            t = (item.findtext("title") or "").strip()
            d = re.sub(r"<[^>]+>", "", item.findtext("description") or "")
            if t:
                items.append({
                    "title":   html.unescape(t),
                    "snippet": html.unescape(d)[:300],
                    "link":    (item.findtext("link") or "").strip(),
                    "source":  (root.findtext("channel/title") or url).strip(),
                })
        for entry in root.findall("a:entry", ns):
            t = (entry.findtext("a:title", "", ns) or "").strip()
            s = re.sub(r"<[^>]+>", "", entry.findtext("a:summary", "", ns) or "")
            lel = entry.find("a:link", ns)
            if t:
                items.append({
                    "title":   html.unescape(t),
                    "snippet": html.unescape(s)[:300],
                    "link":    lel.get("href","") if lel is not None else "",
                    "source":  (root.findtext("a:title", url, ns) or url).strip(),
                })
        return items
    except Exception as ex:
        print(f"  ⚠ RSS skip ({url[:50]}): {ex}")
        return []


def fetch_all_news() -> list[dict]:
    print("📡 Fetching RSS feeds...")
    all_items, seen_titles = [], set()
    for url in RSS_FEEDS:
        for item in fetch_rss(url):
            key = hashlib.md5(item["title"].lower().strip().encode()).hexdigest()
            if key not in seen_titles:
                seen_titles.add(key)
                item["_hash"] = key
                all_items.append(item)
        time.sleep(0.4)
    print(f"  {len(all_items)} unique stories fetched")
    return all_items


def filter_new_stories(all_stories: list[dict], seen_cache: set) -> list[dict]:
    """Return only stories not seen in previous runs."""
    new = [s for s in all_stories if s["_hash"] not in seen_cache]
    print(f"  {len(new)} new stories since last run ({len(all_stories) - len(new)} already seen)")
    return new


# ── JSON RECOVERY HELPER ──────────────────────────────────────
def _safe_json_loads(raw: str):
    """
    Attempt to parse JSON from Claude's raw output with progressive fallbacks.
    Handles: truncation, unescaped quotes, smart quotes, literal newlines in
    strings, stray text before/after the JSON object or array.
    """
    # 1. Direct parse (fastest, handles well-formed output)
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        pass

    # 2. json_repair — handles truncated JSON, missing closing brackets, etc.
    if repair_json:
        try:
            repaired = repair_json(raw, return_objects=True)
            if repaired is not None and repaired != "" and repaired != [] and repaired != {}:
                return repaired
        except Exception:
            pass

    # 3. Extract the outermost JSON object or array and retry
    for pattern in (r'\[.*\]', r'\{.*\}'):
        match = re.search(pattern, raw, re.DOTALL)
        if match:
            candidate = match.group()
            try:
                return json.loads(candidate)
            except json.JSONDecodeError:
                pass
            # 4. Sanitise common Claude output issues inside the extracted block
            cleaned = candidate
            cleaned = cleaned.replace('\u201c', '"').replace('\u201d', '"')   # smart double quotes
            cleaned = cleaned.replace('\u2018', "'").replace('\u2019', "'")   # smart single quotes
            cleaned = re.sub(r'(?<!\\)\n', ' ', cleaned)                      # literal newlines in strings
            cleaned = re.sub(r'(?<!\\)\r', '', cleaned)                       # carriage returns
            cleaned = re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f]', '', cleaned)  # stray control chars
            try:
                return json.loads(cleaned)
            except json.JSONDecodeError:
                pass
            # 5. Last resort: json_repair on the sanitised candidate
            if repair_json:
                try:
                    repaired = repair_json(cleaned, return_objects=True)
                    if repaired is not None and repaired != "" and repaired != [] and repaired != {}:
                        return repaired
                except Exception:
                    pass

    raise json.JSONDecodeError("All JSON recovery attempts failed", raw, 0)


# ── STEP 1: CURATE — score and rank all new stories ───────────
def curate_stories(new_stories: list[dict]) -> list[dict]:
    """
    Send all new stories to Claude for scoring.
    Claude ranks them by satirical potential and reader relevance.
    Returns stories sorted best-first, each with a section tag and score.
    """
    if not new_stories:
        return []

    print(f"\n🔍 Curating {len(new_stories)} new stories...")

    # Cap at 80 stories to stay within token limits
    stories_to_score = new_stories[:80]

    news_blob = "\n".join(
        f"[{i+1}] {s['title']} — {s['source']}\n    {s['snippet'][:200]}"
        for i, s in enumerate(stories_to_score)
    )

    prompt = f"""You are the editorial director of DumbCapital, a satirical North American VC and M&A news site. Your readers are smart, financially literate people who enjoy seeing bad deals and delusional founders called out with wit and precision.

Here are {len(stories_to_score)} fresh news stories. Your job is to score and categorise each one.

STORIES:
{news_blob}

SCORING CRITERIA — score each story 1-10 for satirical potential based on:
- Absurd valuation relative to revenue or traction
- Obvious red flags dressed up in PR language ("synergies", "platform", "AI-native")
- Serial bad acquirers or repeat offenders
- Deals that are clearly doomed based on history
- Hilarious justifications for terrible decisions
- High reader relevance: affects real companies, real money, North American focus
- Stories where the gap between the spin and the reality is widest

Return ONLY a valid JSON array — no markdown, no preamble. Each element:
{{
  "index": 1,
  "score": 8,
  "section": "vc",
  "reason": "One sentence on why this is satirically rich"
}}

section must be one of: vc | ma | pe | unicorn | opinion
Only include stories scoring 5 or higher.
Sort by score descending."""

    try:
        body = json.dumps({
            "model":      "claude-haiku-4-5-20251001",
            "max_tokens": 2000,
            "messages":   [{"role": "user", "content": prompt}],
        }).encode()
        req = urllib.request.Request(
            "https://api.anthropic.com/v1/messages",
            data=body,
            headers={
                "x-api-key":         ANTHROPIC_API_KEY,
                "anthropic-version": "2023-06-01",
                "content-type":      "application/json",
            },
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=60) as r:
            data = json.loads(r.read())
        raw = data["content"][0]["text"].strip()
        raw = re.sub(r"^```(?:json)?\s*", "", raw)
        raw = re.sub(r"\s*```$", "", raw)
        scored = _safe_json_loads(raw)

        # Attach scores and sections back to story dicts
        ranked = []
        for entry in scored:
            idx = entry.get("index", 0) - 1
            if 0 <= idx < len(stories_to_score):
                story = dict(stories_to_score[idx])
                story["_score"]   = entry.get("score", 5)
                story["_section"] = entry.get("section", "vc")
                story["_reason"]  = entry.get("reason", "")
                ranked.append(story)

        ranked.sort(key=lambda x: x["_score"], reverse=True)
        print(f"  {len(ranked)} stories scored ≥5 — top pick: [{ranked[0]['_score']}/10] {ranked[0]['title'][:60]}...")
        return ranked

    except Exception as ex:
        print(f"  ⚠ Curation failed: {ex} — falling back to unscored stories")
        # Fallback: return stories unscored
        for s in stories_to_score:
            s["_score"] = 5
            s["_section"] = "vc"
            s["_reason"] = ""
        return stories_to_score


# ── STEP 2: WRITE — one article per section ───────────────────
def claude_haiku(prompt: str, max_tokens: int = 2000) -> str:
    body = json.dumps({
        "model":      "claude-haiku-4-5-20251001",
        "max_tokens": max_tokens,
        "messages":   [{"role": "user", "content": prompt}],
    }).encode()
    req = urllib.request.Request(
        "https://api.anthropic.com/v1/messages",
        data=body,
        headers={
            "x-api-key":         ANTHROPIC_API_KEY,
            "anthropic-version": "2023-06-01",
            "content-type":      "application/json",
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=60) as r:
        data = json.loads(r.read())
    return data["content"][0]["text"].strip()


def write_article(section: dict, story: dict) -> dict | None:
    """Write a satirical article about a specific pre-selected story."""
    import random
    kicker = random.choice(section["kickers"])

    prompt = f"""You are the lead writer at DumbCapital — a satirical financial news site covering North American VC and M&A. Dry, precise, merciless toward bad deals.

SECTION: {section['label']}
STORY TO WRITE ABOUT:
Headline: {story['title']}
Source: {story['source']}
Details: {story['snippet']}
Why it's satirically rich: {story.get('_reason', 'Obvious red flags')}

Write a satirical article of 400-600 words about this specific story. Make it ONLY on real facts from the headline and details above — do not invent numbers or deal terms. Editorialize aggressively. Each paragraph should be 3-5 sentences. Depth and specificity beat brevity — give the reader something to actually think about.

{{
  "headline": "Punchy satirical headline, under 15 words, newspaper style",
  "subheadline": "One dry sentence delivering the punchline or core skepticism",
  "kicker": "{kicker}",
  "section": "{section['key']}",
  "tag": "one of: vc | ma | pe | unicorn | flop | opinion",
  "source_headline": "{story['title'].replace('"', '')}",
  "source_url": "{story.get('link', '')}",
  "source_name": "{story['source']}",
  "dumb_rating": 4,
  "dumb_rating_label": "2-4 word funny label (e.g. 'Criminally Optimistic')",
  "body_html": "<p>Para 1 — establish the deal, who is involved, and the headline number (valuation, deal size, fund size).</p><p>Para 2 — provide context: what does this company actually do, and what is their actual revenue or traction relative to the valuation?</p><p>Para 3 — dig into the history or pattern: have these players done this before? What happened?</p><p>Para 4 — mock the press release language and justification. Quote the buzzwords and translate them.</p><p>Para 5 — examine what could go wrong, or what the track record of similar deals suggests.</p><p>Para 6 — broader industry commentary: what does this deal say about the current state of VC/M&A?</p><p>Para 7 — end with a killer dry kicker line that lands the satirical point.</p>",
  "glossary_term": "One deal-speak term from this story",
  "glossary_definition": "Your one-sentence satirical definition",
  "published": true
}}"""

    for attempt in range(1, 4):  # up to 3 attempts
        try:
            raw = claude_haiku(prompt, max_tokens=2500)
            raw = re.sub(r"^```(?:json)?\s*", "", raw)
            raw = re.sub(r"\s*```$", "", raw)
            raw = raw.strip()
            article = _safe_json_loads(raw)
            print(f"    ✓ [{section['key'].upper()}] {article.get('headline','')[:65]}...")
            return article
        except Exception as ex:
            print(f"    ✗ Attempt {attempt}/3 failed for {section['label']}: {ex}")
            if attempt < 3:
                time.sleep(2)
    return None

# ── SAVE POST ─────────────────────────────────────────────────
def save_post(article: dict) -> None:
    POSTS_DIR.mkdir(exist_ok=True)
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    slug  = re.sub(r"[^a-z0-9]+", "-", article.get("headline","post").lower()).strip("-")[:55]
    path  = POSTS_DIR / f"{today}-{slug}.json"
    if path.exists():
        path = POSTS_DIR / f"{today}-{slug}-b.json"
    article["date"] = today
    article["slug"] = slug
    path.write_text(json.dumps(article, indent=2, ensure_ascii=False))
    print(f"    💾 {path.name}")


# ── MAIN ──────────────────────────────────────────────────────
def main():
    print("🗞  DumbCapital Bot — Smart Curation Edition\n")

    # Load seen cache
    seen_cache = load_seen_cache()
    print(f"📋 Seen cache: {len(seen_cache)} previously processed stories")

    # Fetch all news
    all_stories = fetch_all_news()
    if not all_stories:
        print("❌ No news fetched."); return

    # Filter to only new stories
    new_stories = filter_new_stories(all_stories, seen_cache)

    if len(new_stories) < 4:
        print("⚠ Fewer than 4 new stories — using all available stories instead")
        new_stories = all_stories

    # Curate: score and rank all new stories
    ranked_stories = curate_stories(new_stories)
    if not ranked_stories:
        print("❌ Curation returned no results."); return

    # For each section, pick the highest-ranked story assigned to that section
    # Fall back to highest overall score if no section-specific match
    written = 0
    used_indices: set = set()

    for section in SECTIONS:
        print(f"\n📝 {section['label']}")

        # Find best story for this section (not already used)
        story = None
        # First pass: stories tagged for this section
        for s in ranked_stories:
            if s.get("_section") == section["key"] and id(s) not in used_indices:
                story = s
                break
        # Second pass: any untagged high-scoring story
        if not story:
            for s in ranked_stories:
                if id(s) not in used_indices:
                    story = s
                    break

        if not story:
            print(f"  ⚠ No story available for {section['label']}")
            continue

        used_indices.add(id(story))
        print(f"  Selected [{story.get('_score','-')}/10]: {story['title'][:70]}...")

        article = write_article(section, story)
        if article:
            save_post(article)
            written += 1
        time.sleep(1.2)

    # Update seen cache with ALL fetched stories (not just used ones)
    # so we never reprocess them even if they weren't picked this run
    for s in all_stories:
        seen_cache.add(s["_hash"])
    save_seen_cache(seen_cache)
    print(f"\n✅ Done — {written} articles written.")
    print(f"   Seen cache updated: {len(seen_cache)} total stories processed.")
    print("   GitHub Actions will now commit and FTP-deploy to Webfundament.")


if __name__ == "__main__":
    main()

