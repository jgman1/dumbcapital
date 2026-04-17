#!/usr/bin/env python3
"""
DumbCapital Content Bot
Configured for: github.com/jgman1/dumbcapital
Deploys to: /home/dumbcapi/public_html/dumbcapital.com/claude/ via FTP

Uses:
  - Free RSS feeds (no API cost)
  - Claude Haiku (cheapest model, ~$0.08-0.20/week at 2 runs/week)

Runs via GitHub Actions every Tuesday and Friday at 8am EST.
"""

import os, json, re, time, html, hashlib
import urllib.request
import urllib.error
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path

# ── CONFIG ────────────────────────────────────────────────────
# API key is loaded from GitHub Secrets — never hardcoded here
ANTHROPIC_API_KEY = os.environ["ANTHROPIC_API_KEY"]
POSTS_DIR = Path("posts")

# ── FREE RSS FEEDS ────────────────────────────────────────────
# No subscription needed — all public feeds
RSS_FEEDS = [
    # VC / Startup funding
    "https://techcrunch.com/category/venture/feed/",
    "https://feeds.feedburner.com/TechCrunch",
    # M&A / Finance news
    "https://feeds.content.dowjones.io/public/rss/mw-topstories",
    "https://feeds.reuters.com/reuters/businessNews",
    # Deals / PE
    "https://www.axios.com/feeds/feed.rss",
    # Canadian / North American focus
    "https://financialpost.com/feed",
    "https://business.financialpost.com/feed",
    # General business
    "https://www.theglobeandmail.com/rss/article/rss.xml",
]

# ── SECTIONS — 1 article each per run ─────────────────────────
SECTIONS = [
    {
        "key":    "vc",
        "label":  "VC Deals",
        "kickers": ["VC Nonsense", "Money Well Burned", "Series WTF", "Burn Rate Report"],
        "focus":  "venture capital funding rounds, Series A B C D raises, startup valuations, "
                  "pre-revenue companies raising massive rounds, VC fund launches",
    },
    {
        "key":    "ma",
        "label":  "M&A Morgue",
        "kickers": ["Deal of the Week", "M&A Morgue", "Synergy Watch", "Merger Theatre"],
        "focus":  "mergers, acquisitions, corporate buyouts, strategic M&A deals, "
                  "failed integrations, acqui-hires, cross-border deals",
    },
    {
        "key":    "pe",
        "label":  "PE Corner",
        "kickers": ["PE Corner", "Value Destruction Watch", "Leverage Report", "Carried Interest"],
        "focus":  "private equity buyouts, leveraged buyouts LBO, roll-up strategies, "
                  "portfolio company layoffs, PE fund returns, secondaries market",
    },
    {
        "key":    "unicorn",
        "label":  "Unicorn Watch",
        "kickers": ["Unicorn Watch", "Billion Dollar Club", "Valuation Theatre", "Imaginary Billions"],
        "focus":  "billion-dollar startup valuations, unicorn companies, down rounds, "
                  "valuation markdowns, unicorn failures, IPO disappointments",
    },
]


# ── FETCH ALL RSS ─────────────────────────────────────────────
def fetch_rss(url: str) -> list[dict]:
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "DumbCapital/1.0 (RSS reader)"})
        with urllib.request.urlopen(req, timeout=12) as r:
            raw = r.read()
        root = ET.fromstring(raw)
        items, ns = [], {"a": "http://www.w3.org/2005/Atom"}

        for item in root.findall(".//item"):  # RSS 2.0
            t = (item.findtext("title") or "").strip()
            d = re.sub(r"<[^>]+>", "", item.findtext("description") or "")
            if t:
                items.append({
                    "title":   html.unescape(t),
                    "snippet": html.unescape(d)[:280],
                    "link":    (item.findtext("link") or "").strip(),
                    "source":  (root.findtext("channel/title") or url).strip(),
                })
        for entry in root.findall("a:entry", ns):  # Atom
            t = (entry.findtext("a:title", "", ns) or "").strip()
            s = re.sub(r"<[^>]+>", "", entry.findtext("a:summary", "", ns) or "")
            l = entry.find("a:link", ns)
            if t:
                items.append({
                    "title":   html.unescape(t),
                    "snippet": html.unescape(s)[:280],
                    "link":    l.get("href","") if l is not None else "",
                    "source":  (root.findtext("a:title", url, ns) or url).strip(),
                })
        return items
    except Exception as ex:
        print(f"  ⚠ RSS skip ({url[:50]}): {ex}")
        return []


def fetch_all_news() -> list[dict]:
    print("📡 Fetching RSS feeds...")
    all_items, seen = [], set()
    for url in RSS_FEEDS:
        for item in fetch_rss(url):
            key = hashlib.md5(item["title"].lower().encode()).hexdigest()
            if key not in seen:
                seen.add(key)
                all_items.append(item)
        time.sleep(0.4)
    print(f"  {len(all_items)} unique stories collected")
    return all_items


# ── CALL CLAUDE HAIKU ─────────────────────────────────────────
def claude_haiku(prompt: str, max_tokens: int = 950) -> str:
    """
    Calls claude-haiku-4-5-20251001 — Anthropic's cheapest model.
    Cost per call: ~$0.002–0.005 USD.
    At 4 sections × 2 runs/week = ~$0.04–0.10/week total.
    """
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


# ── WRITE ONE ARTICLE ─────────────────────────────────────────
def write_article(section: dict, news: list[dict], used: set) -> dict | None:
    import random
    candidates = [n for n in news if n.get("link") not in used][:45]
    if not candidates:
        candidates = news[:45]

    news_blob = "\n".join(
        f"[{i+1}] {item['title']} — {item['source']}\n    {item['snippet']}"
        for i, item in enumerate(candidates)
    )
    kicker = random.choice(section["kickers"])

    prompt = f"""You are the lead writer at DumbCapital — a satirical financial news site covering North American VC and M&A. Your voice: dry, precise, merciless toward bad deals. You write like a seasoned financial journalist who has stopped pretending any of this makes sense.

SECTION: {section['label']}
FOCUS TOPICS: {section['focus']}

THIS WEEK'S NEWS:
{news_blob}

INSTRUCTIONS:
1. Pick the single most skepticism-worthy story from the list that fits this section's focus. Prioritise: absurd valuations, suspicious "synergies", doomed deals, hilarious justifications, serial bad acquirers, or pre-revenue companies raising enormous rounds.
2. Write a sharp satirical article. Base it ONLY on real facts from the headline/snippet — do not invent numbers or deal terms. You may editorialize aggressively and draw conclusions from patterns.
3. Return ONLY a valid JSON object. No markdown fences, no preamble, nothing else.

Required JSON keys:
{{
  "headline": "Punchy satirical headline, under 15 words, newspaper style",
  "subheadline": "One dry sentence delivering the punchline or core skepticism",
  "kicker": "{kicker}",
  "section": "{section['key']}",
  "tag": "one of: vc | ma | pe | unicorn | flop | opinion",
  "source_headline": "The exact original headline this is based on",
  "source_url": "URL if available in the news item, else empty string",
  "source_name": "Publication or feed name",
  "dumb_rating": 4,
  "dumb_rating_label": "2-4 word funny label (e.g. 'Criminally Optimistic', 'Textbook Hubris')",
  "body_html": "<p>Paragraph 1 — establish the deal and what's wrong with it.</p><p>Paragraph 2 — dig into the numbers or the history.</p><p>Paragraph 3 — mock the press release language or justification.</p><p>Paragraph 4 — end with a killer dry kicker line.</p>",
  "glossary_term": "One deal-speak term that appears in this story",
  "glossary_definition": "Your one-sentence satirical definition of that term",
  "published": true
}}"""

    try:
        raw = claude_haiku(prompt)
        raw = re.sub(r"^```(?:json)?\s*","",raw)
        raw = re.sub(r"\s*```$","",raw)
        article = json.loads(raw)
        if article.get("source_url"):
            used.add(article["source_url"])
        print(f"    ✓ [{section['key'].upper()}] {article.get('headline','')[:65]}...")
        return article
    except Exception as ex:
        print(f"    ✗ Failed ({section['label']}): {ex}")
        return None


# ── SAVE POST ─────────────────────────────────────────────────
def save_post(article: dict) -> None:
    POSTS_DIR.mkdir(exist_ok=True)
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    slug  = re.sub(r"[^a-z0-9]+" ,"-", article.get("headline","post").lower()).strip("-")[:55]
    path  = POSTS_DIR / f"{today}-{slug}.json"
    if path.exists():
        path = POSTS_DIR / f"{today}-{slug}-b.json"
    article["date"] = today
    article["slug"] = slug
    path.write_text(json.dumps(article, indent=2, ensure_ascii=False))
    print(f"    💾 {path.name}")


# ── MAIN ──────────────────────────────────────────────────────
def main():
    print("🗞  DumbCapital Bot — github.com/jgman1/dumbcapital\n")
    news = fetch_all_news()
    if not news:
        print("❌ No news — check RSS feeds or network."); return

    used: set = set()
    written = 0
    for section in SECTIONS:
        print(f"\n📝 {section['label']}")
        art = write_article(section, news, used)
        if art:
            save_post(art)
            written += 1
        time.sleep(1.2)  # gentle pause between API calls

    print(f"\n✅ Done — {written} articles written.")
    print("   GitHub Actions will now commit posts and FTP-deploy to Webfundament.")


if __name__ == "__main__":
    main()
