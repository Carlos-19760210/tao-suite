# -*- coding: utf-8 -*-
# Cartoes-conceito 16:9 (1920x1080) p/ o video do CRM — padrao navy/bronze da campanha.
import os, subprocess, sys
sys.stdout.reconfigure(encoding='utf-8')

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
OUT    = os.path.join(os.path.dirname(os.path.abspath(__file__)), "capturas-crm")
TMP    = os.path.join(os.path.dirname(os.path.abspath(__file__)), "_frame_crm.html")

CSS = """
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1920px;height:1080px;overflow:hidden}
body{font-family:'Segoe UI',Arial,sans-serif}
.navy{background:linear-gradient(155deg,#152C42,#0E2233);width:1920px;height:1080px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  color:#fff;text-align:center;padding:120px}
.eyebrow{font-family:Consolas,monospace;font-size:26px;letter-spacing:8px;color:#8FA3B5;margin-bottom:44px}
.grande{font-family:Georgia,'Times New Roman',serif;font-size:88px;line-height:1.2;font-weight:700}
.bronze{color:#B38E6C}
.sub{font-size:34px;line-height:1.6;color:#C7D2DC;margin-top:44px;max-width:1100px}
.regua{width:140px;height:6px;background:#B38E6C;border-radius:3px;margin:56px auto 0}
"""

def card(eyebrow, grande, sub):
    return (f"<div class='navy'><div class='eyebrow'>{eyebrow}</div>"
            f"<div class='grande'>{grande}</div>"
            f"<div class='sub'>{sub}</div><div class='regua'></div></div>")

FRAMES = [
    ("crm-00-abertura", card("TAO NEO &middot; CRM",
        "O CRM que trabalha<br><span class='bronze'>enquanto voc&ecirc; atende.</span>",
        "Funis, automa&ccedil;&otilde;es, an&aacute;lise e controle — do jeito da sua opera&ccedil;&atilde;o.")),
    ("crm-09-multiwhats", card("TAO NEO &middot; CRM",
        "V&aacute;rios n&uacute;meros de WhatsApp.<br><span class='bronze'>Uma s&oacute; opera&ccedil;&atilde;o.</span>",
        "Cada n&uacute;mero com seu funil e sua equipe — vendas, p&oacute;s-vendas, unidades — tudo no mesmo painel, sem trocar de tela.")),
    ("crm-10-sem-intervencao", card("TAO NEO &middot; CRM",
        "Regras por fase.<br><span class='bronze'>O funil anda sozinho.</span>",
        "Mensagem de boas-vindas, follow-up no prazo, mover ao responder, encerrar sem resposta — sem depender de algu&eacute;m lembrar.")),
    ("crm-11-sob-medida", card("TAO NEO &middot; SOB MEDIDA",
        "Do jeito do<br><span class='bronze'>seu neg&oacute;cio.</span>",
        "Funis, fases, campos, automa&ccedil;&otilde;es e perfis configurados pra sua opera&ccedil;&atilde;o. E o que n&atilde;o existir, customizamos pra voc&ecirc;.")),
    ("crm-12-fechamento", card("TAO NEO",
        "Uma conversa.<br><span class='bronze'>Um sistema.</span>",
        "solucoesetao.com.br/tao-neo")),
]

ok = 0
for nome, body in FRAMES:
    html = f"<!doctype html><html><head><meta charset='utf-8'><style>{CSS}</style></head><body>{body}</body></html>"
    open(TMP, "w", encoding="utf-8").write(html)
    png = os.path.join(OUT, nome + ".png")
    r = subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars",
                        "--window-size=1920,1080", f"--screenshot={png}",
                        "file:///" + TMP.replace("\\", "/")], capture_output=True, timeout=60)
    if os.path.exists(png) and os.path.getsize(png) > 10000:
        ok += 1; print("OK:", nome)
    else:
        print("FALHOU:", nome, r.stderr.decode(errors="replace")[-150:])
print(f"{ok}/{len(FRAMES)} cartoes gerados")
