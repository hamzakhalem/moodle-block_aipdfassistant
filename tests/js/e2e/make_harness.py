import re, json, sys, importlib.util, os
spec = importlib.util.spec_from_file_location('langgen', '/home/claude/build/langgen.py')
lg = importlib.util.module_from_spec(spec); spec.loader.exec_module(lg)
S = lg.S
ROOT = '/home/claude/aipdfassistant'

def render_template(lang_idx, maxmb='20', canspeak=True, uniqid='bpa-7', title='Study assistant', selected='ar'):
    tpl = open(ROOT + '/templates/chat.mustache', encoding='utf-8').read()
    tpl = re.sub(r'\{\{!.*?\}\}', '', tpl, flags=re.S)
    def strsub(m):
        parts = [p.strip() for p in m.group(1).split(',')]
        key = parts[0]; text = S[key][lang_idx]
        if len(parts) > 2:
            val = parts[2].replace('{{maxmb}}', maxmb)
            text = text.replace('{$a}', val)
        return text
    tpl = re.sub(r'\{\{#str\}\}(.*?)\{\{/str\}\}', strsub, tpl, flags=re.S)
    tpl = re.sub(r'\{\{#canspeak\}\}(.*?)\{\{/canspeak\}\}', lambda m: m.group(1) if canspeak else '', tpl, flags=re.S)
    langs = [('ar','العربية'),('fr','Français'),('en','English'),('es','Español'),('de','Deutsch'),('it','Italiano'),('tr','Türkçe'),('ru','Русский'),('zh','中文')]
    def langsub(m):
        inner = m.group(1); out = ''
        for code, name in langs:
            s = inner.replace('{{code}}', code).replace('{{name}}', name)
            s = re.sub(r'\{\{#selected\}\}(.*?)\{\{/selected\}\}', lambda mm: mm.group(1) if code == selected else '', s)
            out += s
        return out
    tpl = re.sub(r'\{\{#languages\}\}(.*?)\{\{/languages\}\}', langsub, tpl, flags=re.S)
    return tpl.replace('{{uniqid}}', uniqid).replace('{{title}}', title).replace('{{maxmb}}', maxmb)

def js_strings(lang_idx):
    keys = ['assistant','you','dropTitle','dropHint','pickDocument','recent','courseFiles','noDocs','placeholder','placeholderNoDoc','send','attach','mic','micStop','expand','collapse','clear','removeDoc','copy','copied','listen','stop','retry','summarize','explain','translate','quiz','filename','speak','translateTo','uploading','reading','thinking','pages','page','ready','emptyTitle','emptyHint','errorSize','errorType','errorGeneric','lowtext','noticePartial','noticeTranslate','cached','speaking','loading','audioFailed','browserVoice','docAttached','uploadFailed']
    out = {}
    for k in keys:
        v = S['js_' + k][lang_idx]
        out[k] = v.replace('{$a}', '%a%')
    return out

def build(lang, out):
    idx = {'en':0,'ar':1,'fr':2}[lang]
    html = render_template(idx, selected='ar' if lang != 'ar' else 'fr')
    css = open(ROOT + '/styles.css', encoding='utf-8').read()
    mods = ['icons','markdown','api','chat']
    scripts = ''
    for m in mods:
        scripts += "<script>window.__mod='block_aipdfassistant/%s';</script>\n<script>%s</script>\n" % (m, open(ROOT + '/amd/src/%s.js' % m, encoding='utf-8').read())
    cfg = {'uniqid':'bpa-7','contextid':55,'courseid':2,'maxbytes':20*1048576,'maxmb':'20','uploadurl':'/blocks/aipdfassistant/upload.php','canspeak':True,'browsertts':False,'gender':'female','allowcourse':True,'sesskey':'abc123','lang':lang,'strings':js_strings(idx)}
    tplp = open(ROOT + '/templates/picker.mustache', encoding='utf-8').read()
    tplp = re.sub(r'\{\{!.*?\}\}', '', tplp, flags=re.S)
    tplpicker = json.dumps(tplp, ensure_ascii=False)
    strs = json.dumps({k: S[k][idx].replace('{$a}', '%a%') for k in S if k.startswith('js_')}, ensure_ascii=False)
    d = 'rtl' if lang == 'ar' else 'ltr'
    page = f'''<!doctype html><html lang="{lang}" dir="{d}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>harness</title>
<style>
body{{font-family:-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Naskh Arabic",sans-serif;background:#eef1f4;margin:0;padding:24px;color:#212529}}
.sr-only{{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}}
.card{{width:330px;background:#fff;border:1px solid #dee2e6;border-radius:.5rem;margin:0 auto}}
.card h3{{font-size:1.1rem;margin:0;padding:.75rem 1rem;border-bottom:1px solid #dee2e6}}
.card .content{{padding:.75rem}}
.wide .card{{width:560px}}
{css}
</style></head><body>
<div class="card"><h3>{S['blocktitle_default'][idx]}</h3><div class="content">{html}</div></div>
<script>
window.__cfg = {json.dumps(cfg, ensure_ascii=False)};
window.__reg = {{}};
window.__mods = {{}};
window.define = function(deps, factory) {{
  const args = deps.map(d => window.__reg[d]);
  window.__reg[window.__mod] = factory.apply(null, args);
}};
// Mock ajax: scenario handlers installed by tests on window.__handlers
window.__calls = [];
window.__handlers = {{}};
window.__reg['core/templates'] = (function() {{
  const TPL = {tplpicker};
  const STR = {strs};
  function lookup(stack, name) {{
    for (let i = stack.length - 1; i >= 0; i--) {{
      const c = stack[i];
      if (c && typeof c === 'object' && name in c) {{ return c[name]; }}
    }}
    return undefined;
  }}
  const esc = (v) => String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  function render(tpl, stack) {{
    let out = '', pos = 0;
    const re = /\{{\{{([#^/]?)\s*([\w.]+)([^}}]*)\}}\}}/g;
    let m;
    while ((m = re.exec(tpl)) !== null) {{
      out += tpl.slice(pos, m.index);
      pos = re.lastIndex;
      const kind = m[1], name = m[2];
      if (!kind) {{ const v = lookup(stack, name); out += v === undefined ? '' : esc(v); continue; }}
      // find the matching closing tag, honouring nesting of the same name
      let depth = 1, j = pos; const re2 = new RegExp('\\{{\\{{([#^/])\\s*' + name + '[^}}]*\\}}\\}}', 'g'); re2.lastIndex = pos; let n;
      while ((n = re2.exec(tpl)) !== null) {{ depth += n[1] === '/' ? -1 : 1; if (depth === 0) {{ j = n.index; pos = re2.lastIndex; break; }} }}
      const inner = tpl.slice(m.index + m[0].length, j);
      re.lastIndex = pos;
      if (name === 'str') {{
        const parts = render(inner, stack).split(',').map((x) => x.trim());
        let text = STR[parts[0]] || parts[0];
        if (parts.length > 2) {{ text = text.replace('%a%', parts[2]); }}
        out += text; continue;
      }}
      const v = lookup(stack, name);
      if (kind === '#') {{
        if (Array.isArray(v)) {{ v.forEach((item) => {{ out += render(inner, stack.concat([item])); }}); }}
        else if (v) {{ out += render(inner, stack.concat([typeof v === 'object' ? v : {{}}])); }}
      }} else if (!v || (Array.isArray(v) && !v.length)) {{ out += render(inner, stack); }}
    }}
    return out + tpl.slice(pos);
  }}
  return {{
    renderForPromise: function(name, ctx) {{ window.__renders = (window.__renders || 0) + 1; return Promise.resolve({{html: render(TPL, [ctx]), js: ''}}); }},
    replaceNodeContents: function(node, html) {{ node.innerHTML = html; }}
  }};
}})();
window.__reg['core/ajax'] = {{ call: function(reqs) {{
  return reqs.map(function(r) {{
    window.__calls.push({{name: r.methodname, args: JSON.parse(JSON.stringify(r.args))}});
    const h = window.__handlers[r.methodname];
    return new Promise(function(resolve, reject) {{
      setTimeout(function() {{
        try {{ const v = h ? h(r.args) : {{success: true}}; if (v && v.__reject) reject(v.__reject); else resolve(v); }} catch (e) {{ reject(e); }}
      }}, 40);
    }});
  }});
}} }};
</script>
{scripts}
<script>window.__chat = window.__reg['block_aipdfassistant/chat']; window.__start = function() {{ window.__chat.init(window.__cfg); }};</script>
</body></html>'''
    open(out, 'w', encoding='utf-8').write(page)

build('en', '/home/claude/build/site/en.html')
build('ar', '/home/claude/build/site/ar.html')
build('fr', '/home/claude/build/site/fr.html')
print('harness built')
