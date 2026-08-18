# -*- coding: utf-8 -*-
"""Smoke-test pós-RLS: confirma que a operação continua de pé e que o anônimo foi bloqueado.
Rodar IMEDIATAMENTE depois de executar rls_enable_v1.sql no SQL Editor.
Uso: python rls_smoke_test.py
"""
import requests, sys
sys.stdout.reconfigure(encoding="utf-8")
SB = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = open(r"C:\Users\carlo\FCertaSync\supabase_key.txt").read().strip()
H_SECRET = {"apikey": KEY, "Authorization": "Bearer " + KEY}

ok = fail = 0
def chk(nome, cond, extra=""):
    global ok, fail
    print(("  ✔ " if cond else "  ✘ ") + nome + (f"  {extra}" if extra else ""))
    ok += cond; fail += not cond

print("== 1. Backend (chave secret DEVE continuar lendo tudo) ==")
for t in ["clientes", "crm_cards", "orcamentos", "catalogo", "caixa_vendas",
          "campanhas", "historico", "contas_pagar", "contas_categorias", "lab_lotes_mp"]:
    r = requests.get(f"{SB}/{t}?select=*&limit=1", headers=H_SECRET, timeout=20)
    chk(f"secret lê {t}", r.status_code == 200 and isinstance(r.json(), list),
        f"HTTP {r.status_code}")

print("\n== 2. Escrita com secret (INSERT+DELETE em tabela inócua) ==")
r = requests.post(f"{SB}/contas_categorias", headers={**H_SECRET,
                  "Content-Type": "application/json", "Prefer": "return=representation"},
                  json={"cliente_id": "00000000-0000-0000-0000-000000000000",
                        "nome": "TESTE RLS (apagar)", "ordem": 99}, timeout=20)
chk("secret escreve", r.status_code < 300, f"HTTP {r.status_code}")
if r.status_code < 300:
    rid = r.json()[0]["id"]
    r2 = requests.delete(f"{SB}/contas_categorias?id=eq.{rid}", headers=H_SECRET, timeout=20)
    chk("secret apaga", r2.status_code < 300)

print("\n== 3. Anônimo (sem apikey) DEVE ser bloqueado ==")
r = requests.get(f"{SB}/clientes?select=id&limit=1", timeout=20)
chk("sem apikey bloqueado", r.status_code in (401, 403), f"HTTP {r.status_code}")

print("\n== 4. Portal no ar (páginas respondem) ==")
for url in ["https://solucoesetao.com.br/robos/login/",
            "https://solucoesetao.com.br/"]:
    r = requests.get(url, timeout=30)
    chk(url.split("//")[1][:40], r.status_code == 200, f"HTTP {r.status_code}")

print(f"\nRESULTADO: {ok} ✔ / {fail} ✘")
if fail:
    print("⚠ Se algo do backend falhou: rollback pontual com")
    print("  ALTER TABLE public.<tabela> DISABLE ROW LEVEL SECURITY;")
sys.exit(1 if fail else 0)
