/* Avesta IT assistant — matcher, JavaScript edition.
 *
 * A line-for-line port of the matching in it-help.php, so the preview answers
 * exactly as the live site does. test_matcher_parity.js runs every frozen
 * routing case through both and fails on any difference, in topic, picked-up
 * words, corrections, second topics or choices. Change one, change the other.
 */
(function (root) {
  var STOP = ['a','an','the','is','am','are','was','were','be','been','being','my','our',
    'your','his','her','its','it','this','that','these','those','to','of','on','in','at','for','with',
    'and','or','but','so','i','me','we','us','you','they','them','he','she','will','would','do','does',
    'did','have','has','had','can','could','should','just','really','very','please','help','hi',
    'hello','there','how','what','why','when','where','which','who','get','got','keep','keeps'];
  var isStop = {}; STOP.forEach(function (w) { isStop[w] = true; });

  function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  function normalise(t) {
    t = String(t).toLowerCase().replace(/[\u2019\u2018`\u00B4]/g, "'");
    var contract = [
      ["can't",'cannot'],['cant','cannot'],['can not','cannot'],
      ["won't",'will not'],['wont','will not'],
      ["don't",'do not'],['dont','do not'],["didn't",'did not'],['didnt','did not'],
      ["doesn't",'does not'],['doesnt','does not'],["isn't",'is not'],['isnt','is not'],
      ["wasn't",'was not'],['wasnt','was not'],["couldn't",'could not'],['couldnt','could not'],
      ["haven't",'have not'],['havent','have not'],["aren't",'are not'],['arent','are not'],
      ["i'm",'i am'],["it's",'it is']
    ];
    contract.forEach(function (p) {
      t = t.replace(new RegExp('(?<![a-z])' + esc(p[0]) + '(?![a-z])', 'g'), p[1]);
    });
    var compound = [
      [/\bwi[\s\-]?fi\b/g, 'wifi'], [/\be[\s\-]mail/g, 'email'], [/\blog[\s\-]in\b/g, 'login'],
      [/\bsign[\s\-]in\b/g, 'signin'], [/\bset[\s\-]up\b/g, 'setup'], [/\bback[\s\-]ups?\b/g, 'backup'],
      [/\bpop[\s\-]?ups?\b/g, 'popup'], [/\bload[\s\-]?shedding\b/g, 'loadshedding'],
      [/\bshutdown\b/g, 'shut down'], [/\bpc\b/g, 'computer'], [/\bmodem\b/g, 'router'],
      [/\b(smart|cell|mobile)\s?phone\b/g, 'phone']
    ];
    compound.forEach(function (p) { t = t.replace(p[0], p[1]); });
    return t;
  }

  function undouble(x) {
    var n = x.length;
    if (n >= 3 && x[n-1] === x[n-2] && 'lsz'.indexOf(x[n-1]) === -1 && 'aeiou'.indexOf(x[n-1]) === -1)
      return x.slice(0, -1);
    return x;
  }
  function stem(w) {
    if (w.length <= 3 || /^\d+$/.test(w)) return w;
    if (w.length >= 6 && w.slice(-3) === 'ing')      w = undouble(w.slice(0, -3));
    else if (w.length >= 5 && w.slice(-3) === 'ied') w = w.slice(0, -3) + 'y';
    else if (w.length >= 5 && w.slice(-3) === 'ies') w = w.slice(0, -3) + 'y';
    else if (w.length >= 5 && w.slice(-2) === 'ed')  w = undouble(w.slice(0, -2));
    else if (w.length >= 5 && w.slice(-2) === 'es')  w = w.slice(0, -2);
    else if (w.slice(-1) === 's' && !/(ss|us|is)$/.test(w)) w = w.slice(0, -1);
    if (w.length >= 4 && w.slice(-1) === 'e') w = w.slice(0, -1);
    return w;
  }
  function split(t) { return normalise(t).split(/[^a-z0-9]+/).filter(Boolean); }
  function tokens(t) { return split(t).map(stem); }

  function levenshtein(a, b) {
    var m = a.length, n = b.length, d = [], i, j;
    for (i = 0; i <= m; i++) { d[i] = [i]; }
    for (j = 0; j <= n; j++) { d[0][j] = j; }
    for (i = 1; i <= m; i++) for (j = 1; j <= n; j++) {
      d[i][j] = Math.min(d[i-1][j] + 1, d[i][j-1] + 1, d[i-1][j-1] + (a[i-1] === b[j-1] ? 0 : 1));
    }
    return d[m][n];
  }

  function Matcher(KB) {
    var idx = { phrases: [], vocab: {} };
    KB.forEach(function (e, i) {
      var seen = {}; idx.phrases[i] = [];
      e.match.forEach(function (phrase) {
        var exact = phrase !== '' && phrase[0] === '=';
        var tok = tokens(exact ? phrase.slice(1) : phrase);
        if (!tok.length) return;
        var meaningful = tok.filter(function (w) { return !isStop[w]; });
        var key = exact ? '=' + tok.join(' ') : (meaningful.join(' ') || tok.join(' '));
        if (seen[key]) return;
        seen[key] = true;
        var core = exact ? tok : (meaningful.length ? meaningful : tok);
        idx.phrases[i].push({ text: phrase.replace(/^=+/, ''), tok: core, exact: exact,
                              weight: 1 + 2 * (core.length - 1) });
        tok.forEach(function (w) { idx.vocab[w] = true; });
      });
    });
    var vocabList = Object.keys(idx.vocab);

    function correct(toks, fixes) {
      return toks.map(function (w, i) {
        if (idx.vocab[w] || w.length < 4 || /^\d+$/.test(w) || isStop[w]) return w;
        var max = w.length >= 8 ? 2 : 1, best = null, bestD = 99, tie = false, cands = [];
        vocabList.forEach(function (v) {
          if (v[0] !== w[0] || Math.abs(v.length - w.length) > max || v.length < 4) return;
          var d = levenshtein(w, v);
          if (d > max) return;
          if (d < bestD) { best = v; bestD = d; tie = false; cands = [v]; }
          else if (d === bestD && v !== best) { tie = true; cands.push(v); }
        });
        if (tie) {
          var longer = cands.filter(function (c) { return c.length > w.length; });
          if (longer.length === 1) { best = longer[0]; tie = false; }
        }
        if (best !== null && !tie) { fixes[i] = best; return best; }
        return w;
      });
    }

    function find(q, p, gap) {
      var n = q.length, k = p.length;
      for (var s = 0; s < n; s++) {
        if (q[s] !== p[0]) continue;
        var used = [s], pos = s, ok = true;
        for (var j = 1; j < k; j++) {
          var found = false;
          for (var x = pos + 1; x <= Math.min(n - 1, pos + 1 + gap); x++) {
            if (q[x] === p[j]) { used.push(x); pos = x; found = true; break; }
          }
          if (!found) { ok = false; break; }
        }
        if (ok) return used;
      }
      return null;
    }

    function rank(question, fixes) {
      var raw = tokens(question);
      if (!raw.length || String(question).trim().length < 3) return [];
      var q = correct(raw, fixes), ranked = [];
      KB.forEach(function (e, i) {
        var score = 0, hits = [];
        (idx.phrases[i] || []).forEach(function (ph) {
          var used = find(q, ph.tok, ph.exact ? 0 : 3);
          if (!used) return;
          var w = ph.weight;
          var guessed = used.some(function (u) { return fixes.hasOwnProperty(u); });
          if (guessed) w *= ph.tok.length === 1 ? 0.5 : 0.75;
          score += w;
          hits.push({ text: ph.text, weight: w, used: used });
        });
        if (score > 0) ranked.push({ i: i, score: score, hits: hits });
      });
      ranked.sort(function (a, b) { return (b.score - a.score) || (a.i - b.i); });
      return ranked;
    }

    var memo = {};
    function readable(st) {
      if (memo.hasOwnProperty(st)) return memo[st];
      for (var i = 0; i < KB.length; i++) for (var j = 0; j < KB[i].match.length; j++) {
        var words = split(KB[i].match[j]);
        for (var k = 0; k < words.length; k++) if (stem(words[k]) === st) return memo[st] = words[k];
      }
      return memo[st] = st;
    }

    function wordsOf(x) { var u = {}; x.hits.forEach(function (h) { h.used.forEach(function (p) { u[p] = 1; }); }); return u; }

    function answer(question) {
      var fixes = {}, r = rank(question, fixes), plain = split(question);
      var lendStems = ['loan','loans','borrow','lend','lender','credit','repayment','repay','cash advance'].map(stem);
      var lend = plain.map(stem).some(function (w) { return lendStems.indexOf(w) !== -1; });

      if (!r.length || r[0].score < 1) return { matched: false, lending: lend, corrected: [] };
      var top = r[0];

      var tied = r.filter(function (x) { return x.score == top.score; });
      if (top.score < 2 && tied.length >= 2) {
        var topWords = wordsOf(top);
        var same = tied.slice(1).filter(function (x) {
          var w = wordsOf(x); return Object.keys(w).some(function (p) { return topWords[p]; });
        });
        if (same.length) {
          return { matched: false, lending: lend, corrected: [],
                   choices: [top].concat(same).slice(0, 4).map(function (x) { return KB[x.i].title; }) };
        }
      }

      var usedTop = wordsOf(top);
      var pos = Object.keys(usedTop).map(Number).sort(function (a, b) { return a - b; });

      var runs = [], run = [];
      pos.forEach(function (p) {
        if (run.length) {
          var last = run[run.length - 1];
          var gapW = plain.slice(last + 1, p);
          if (gapW.length > 2 || gapW.some(function (g) { return !isStop[g]; })) { runs.push(run); run = []; }
        }
        run.push(p);
      });
      if (run.length) runs.push(run);

      var neg = ['will','do','did','does','is','was','are','could','have'];
      var back = [['will not',"won't"],['cannot',"can't"],['do not',"don't"],['did not',"didn't"],
                  ['does not',"doesn't"],['is not',"isn't"],['was not',"wasn't"],['are not',"aren't"],
                  ['could not',"couldn't"],['have not',"haven't"]];
      var keywords = [];
      runs.forEach(function (rn) {
        var from = rn[0];
        if (plain[from] === 'not' && from > 0 && neg.indexOf(plain[from - 1]) !== -1) from--;
        var words = [];
        for (var p = from; p <= rn[rn.length - 1]; p++) {
          words.push(fixes.hasOwnProperty(p) ? readable(fixes[p]) : (plain[p] || ''));
        }
        var txt = words.join(' ');
        // Whole words only: a plain substring swap would turn "this not" into "thisn't"
        back.forEach(function (b) {
          txt = txt.replace(new RegExp('(?<![a-z])' + esc(b[0]) + '(?![a-z])', 'g'), b[1]);
        });
        if (txt && keywords.indexOf(txt) === -1) keywords.push(txt);
      });
      keywords = keywords.slice(0, 4);

      var corrected = [];
      Object.keys(fixes).map(Number).sort(function (a, b) { return a - b; }).forEach(function (p) {
        if (usedTop[p]) corrected.push({ typed: plain[p] || '', read_as: readable(fixes[p]) });
      });

      var also = [];
      r.slice(1).forEach(function (x) {
        if (also.length >= 2) return;
        var fresh = 0;
        x.hits.forEach(function (h) {
          if (!h.used.some(function (p) { return usedTop[p]; })) fresh += h.weight;
        });
        if (fresh >= 1) also.push(KB[x.i].title);
      });

      return { matched: true, entry: KB[top.i], keywords: keywords, also: also,
               corrected: corrected, lending: false };
    }

    function byTitle(t) { for (var i = 0; i < KB.length; i++) if (KB[i].title === t) return KB[i]; return null; }
    function match(q) { var r = rank(q, {}); return (r.length && r[0].score >= 1) ? KB[r[0].i] : null; }

    return { answer: answer, match: match, byTitle: byTitle, tokens: tokens };
  }

  root.AvestaMatcher = Matcher;
  if (typeof module !== 'undefined') module.exports = Matcher;
})(typeof window !== 'undefined' ? window : this);
