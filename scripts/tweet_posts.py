"""
tweet_posts.py — Posts newly generated DumbCapital articles to X.
Reads the latest posts from the posts/ directory, checks which ones
were created today, and tweets each one.
"""

import os
import json
import glob
import hmac
import hashlib
import time
import random
import string
import urllib.request
import urllib.parse
from datetime import date
import base64

# ── CREDENTIALS (from environment) ────────────────────────────
API_KEY             = os.environ["TWITTER_API_KEY"]
API_SECRET          = os.environ["TWITTER_API_SECRET"]
ACCESS_TOKEN        = os.environ["TWITTER_ACCESS_TOKEN"]
ACCESS_TOKEN_SECRET = os.environ["TWITTER_ACCESS_TOKEN_SECRET"]

BASE_URL = "https://api.twitter.com/2/tweets"

# ── OAUTH 1.0a HELPERS ─────────────────────────────────────────
def percent_encode(s: str) -> str:
    return urllib.parse.quote(str(s), safe='')

def oauth_nonce() -> str:
    return ''.join(random.choices(string.ascii_letters + string.digits, k=32))

def oauth_signature(method, url, params, consumer_secret, token_secret):
    sorted_params = '&'.join(
        f"{percent_encode(k)}={percent_encode(v)}"
        for k, v in sorted(params.items())
    )
    base = '&'.join([
        percent_encode(method.upper()),
        percent_encode(url),
        percent_encode(sorted_params),
    ])
    signing_key = f"{percent_encode(consumer_secret)}&{percent_encode(token_secret)}"
    sig = hmac.new(signing_key.encode(), base.encode(), hashlib.sha1).digest()
    return base64.b64encode(sig).decode()

def auth_header(method, url, body_params=None):
    ts    = str(int(time.time()))
    nonce = oauth_nonce()
    oauth_params = {
        'oauth_consumer_key':     API_KEY,
        'oauth_nonce':            nonce,
        'oauth_signature_method': 'HMAC-SHA1',
        'oauth_timestamp':        ts,
        'oauth_token':            ACCESS_TOKEN,
        'oauth_version':          '1.0',
    }
    all_params = {**oauth_params, **(body_params or {})}
    sig = oauth_signature(method, url, all_params, API_SECRET, ACCESS_TOKEN_SECRET)
    oauth_params['oauth_signature'] = sig
    header = 'OAuth ' + ', '.join(
        f'{percent_encode(k)}="{percent_encode(v)}"'
        for k, v in sorted(oauth_params.items())
    )
    return header

def post_tweet(text: str) -> dict:
    body = json.dumps({"text": text}).encode()
    headers = {
        "Authorization":  auth_header("POST", BASE_URL),
        "Content-Type":   "application/json",
    }
    req = urllib.request.Request(BASE_URL, data=body, headers=headers, method="POST")
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.loads(r.read())

# ── ARTICLE URL ────────────────────────────────────────────────
SECTION_MAP = {
    'vc':      'vc',
    'ma':      'ma',
    'pe':      'pe',
    'unicorn': 'unicorn',
    'opinion': 'opinion',
}

def article_url(post: dict) -> str:
    section = post.get('section') or post.get('tag', 'vc')
    slug    = post.get('slug', '')
    return f"https://dumbcapital.com/{section}/{slug}/"

# ── TWEET TEXT ─────────────────────────────────────────────────
EMOJI_MAP = {
    'vc':      '💸',
    'ma':      '🤝',
    'pe':      '📊',
    'unicorn': '🦄',
    'opinion': '🔥',
}

def build_tweet(post: dict) -> str:
    headline = post.get('headline', '')
    url      = article_url(post)
    section  = post.get('section') or post.get('tag', 'vc')
    emoji    = EMOJI_MAP.get(section, '📰')
    rating   = post.get('dumb_rating_label', '')

    # Full tweet with rating and hashtags
    tweet = f"{emoji} {headline}"
    if rating:
        tweet += f"\n\n★ {rating}"
    tweet += f"\n\n{url}"
    tweet += "\n\n#VentureCapital #StartupNews #DumbCapital"
    if len(tweet) <= 280:
        return tweet

    # Fallback: drop rating
    tweet = f"{emoji} {headline}\n\n{url}\n\n#VentureCapital #DumbCapital"
    if len(tweet) <= 280:
        return tweet

    # Fallback: truncate headline to fit
    suffix = f"\n\n{url}\n\n#VentureCapital #DumbCapital"
    max_headline = 280 - len(emoji) - 1 - len(suffix)
    headline = headline[:max_headline - 3] + '...'
    return f"{emoji} {headline}{suffix}"

# ── MAIN ───────────────────────────────────────────────────────
def main():
    today = date.today().isoformat()
    post_files = glob.glob("posts/*.json")

    # Find posts created today
    todays_posts = []
    for path in post_files:
        try:
            post = json.loads(open(path).read())
            if post.get('date') == today and post.get('published'):
                todays_posts.append(post)
        except Exception:
            continue

    if not todays_posts:
        print(f"No posts found for {today} — nothing to tweet.")
        return

    print(f"Found {len(todays_posts)} post(s) to tweet.")

    for post in todays_posts:
        try:
            tweet = build_tweet(post)
            print(f"\nTweeting: {post.get('headline','')[:60]}...")
            print(f"Tweet text ({len(tweet)} chars):\n{tweet}\n")
            result = post_tweet(tweet)
            tweet_id = result.get('data', {}).get('id', 'unknown')
            print(f"✓ Posted: https://x.com/DumbCapitalNews/status/{tweet_id}")
            time.sleep(3)  # small delay between tweets
        except Exception as ex:
            print(f"✗ Failed to tweet '{post.get('headline','')}': {ex}")

if __name__ == "__main__":
    main()
