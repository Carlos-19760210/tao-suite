# -*- coding: utf-8 -*-
# Captura screenshots das telas do TAO Lab (wp-admin), logado.
import sys, time, os
from playwright.sync_api import sync_playwright

BASE = "https://solucoesetao.com.br"
USER = "carlos.carv.almeida@gmail.com"
PWD  = "Linara.01"
OUT  = r"C:\Users\carlo\AppData\Local\Temp\claude\C--Users-carlo\fc46e316-08dd-4693-9e35-805a6cb6dffe\scratchpad\prints"
os.makedirs(OUT, exist_ok=True)

# só o teste de login + 1 tela se argv[1]==test
TELAS = [
    ("config",       "tao-formula-config"),
    ("ativos",       "tao-formula-ativos"),
    ("prescritores", "tao-formula-prescritores"),
    ("orc_novo",     "tao-formula-orc-novo"),
    ("historico",    "tao-formula-historico"),
    ("estoque_nf",   "tao-formula-estoque-nf"),
    ("estoque_lotes","tao-formula-estoque-lotes"),
    ("estoque_repo", "tao-formula-estoque-repo"),
    ("producao",     "tao-formula-producao"),
    ("livro",        "tao-formula-livro"),
    ("contas_pagar", "tao-formula-contas-pagar"),
]
if len(sys.argv) > 1 and sys.argv[1] == "test":
    TELAS = [("config", "tao-formula-config")]

with sync_playwright() as p:
    br = p.chromium.launch(channel="chrome", headless=True)
    pg = br.new_page(viewport={"width": 1280, "height": 900})
    # login
    pg.goto(BASE + "/wp-login.php", wait_until="domcontentloaded", timeout=60000)
    pg.fill("#user_login", USER)
    pg.fill("#user_pass", PWD)
    pg.click("#wp-submit")
    pg.wait_for_load_state("domcontentloaded", timeout=60000)
    if "wp-login" in pg.url:
        print("FALHA LOGIN — url:", pg.url); br.close(); sys.exit(1)
    print("LOGIN OK ->", pg.url)
    for nome, slug in TELAS:
        try:
            pg.goto(f"{BASE}/wp-admin/admin.php?page={slug}", wait_until="networkidle", timeout=60000)
            time.sleep(2.5)  # espera AJAX das tabelas
            path = os.path.join(OUT, f"{nome}.png")
            pg.screenshot(path=path, full_page=True)
            print(f"  {nome}: {os.path.getsize(path)//1024} KB")
        except Exception as e:
            print(f"  {nome}: ERRO {e}")
    br.close()
print("FIM")
