-- Ginto Trading Academy: its own lessons (branded facility, not the old /courses).
CREATE TABLE IF NOT EXISTS academy_lessons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(80) NOT NULL DEFAULT 'Foundations',
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  summary VARCHAR(400) NULL,
  body LONGTEXT NULL,
  video_url VARCHAR(300) NULL,
  tier ENUM('free','trader','pro') NOT NULL DEFAULT 'trader',
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug),
  KEY idx_pub (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a few starter lessons (idempotent by slug).
INSERT INTO academy_lessons (module, title, slug, summary, body, tier, is_preview, is_published, sort_order)
SELECT 'Foundations', 'What moves crypto markets', 'what-moves-crypto-markets',
  'The forces behind every candle: supply, demand, liquidity, and narrative.',
  '<p>Before any strategy, you need to know <strong>why</strong> price moves. Crypto is driven by supply and demand, liquidity (how easily you can buy/sell), and narrative (the story the market believes right now).</p><p>In this lesson you will learn to spot who is in control — buyers or sellers — and why chasing a coin that already pumped is how most beginners lose money.</p><ul><li>Supply &amp; demand at a glance</li><li>Liquidity: why thin coins are dangerous</li><li>Narrative &amp; momentum</li></ul>',
  'free', 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM academy_lessons WHERE slug = 'what-moves-crypto-markets');

INSERT INTO academy_lessons (module, title, slug, summary, body, tier, is_preview, is_published, sort_order)
SELECT 'Technical Analysis', 'Reading a candlestick', 'reading-a-candlestick',
  'Open, high, low, close — how one candle tells a story of the fight between buyers and sellers.',
  '<p>A candlestick shows four prices over a period: <em>open, high, low, close</em>. Green means close &gt; open (buyers won); red means the opposite.</p><p>The <strong>body</strong> is the open-to-close range; the <strong>wicks</strong> show rejected extremes. Long lower wicks hint at buyers stepping in; long upper wicks hint at sellers capping the move.</p><p>Practice on the live bot: watch how it reads breakouts of the 24h high on volume.</p>',
  'free', 1, 1, 2
WHERE NOT EXISTS (SELECT 1 FROM academy_lessons WHERE slug = 'reading-a-candlestick');

INSERT INTO academy_lessons (module, title, slug, summary, body, tier, is_preview, is_published, sort_order)
SELECT 'Risk Management', 'Position sizing and stop-losses', 'position-sizing-and-stop-losses',
  'The one habit that separates traders who last from those who blow up.',
  '<p>Winning is optional; surviving is not. Every trade must have a <strong>stop-loss</strong> decided <em>before</em> you enter, and a size small enough that a loss barely dents your account.</p><p>The Ginto bot never holds a position without a resting stop on the exchange. You will learn the same discipline: risk a fixed small percentage, cut losers fast, let winners run.</p>',
  'trader', 0, 1, 3
WHERE NOT EXISTS (SELECT 1 FROM academy_lessons WHERE slug = 'position-sizing-and-stop-losses');

INSERT INTO academy_lessons (module, title, slug, summary, body, tier, is_preview, is_published, sort_order)
SELECT 'The Ginto Bot', 'How the AI decides a trade', 'how-the-ai-decides-a-trade',
  'Walk through a real BUY/SKIP decision — the reasoning, the stop, the target.',
  '<p>The bot proposes one candidate at a time from a deterministic filter, then an AI does a fast risk check: is the momentum real, or is it already parabolic?</p><p>You will see a real decision — entry, exchange-side stop, and target — and learn to copy that risk-first thinking into your own manual trades.</p>',
  'trader', 0, 1, 4
WHERE NOT EXISTS (SELECT 1 FROM academy_lessons WHERE slug = 'how-the-ai-decides-a-trade');

INSERT INTO academy_lessons (module, title, slug, summary, body, tier, is_preview, is_published, sort_order)
SELECT 'PineScript & Automation', 'Turn a strategy into rules', 'turn-a-strategy-into-rules',
  'From a chart idea to a testable PineScript strategy the bot can read.',
  '<p>A good strategy is just clear rules: when to enter, where the stop goes, when to take profit. In this Pro lesson you will write a simple PineScript momentum strategy, have the AI inspect it for bugs and risk, and only then apply it.</p><p>Rule #1 stays: never a strategy without a stop-loss.</p>',
  'pro', 0, 1, 5
WHERE NOT EXISTS (SELECT 1 FROM academy_lessons WHERE slug = 'turn-a-strategy-into-rules');
