#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
BACKUP DIÁRIO do Supabase (projeto Robôs) — dump PRÓPRIO, independente da Supabase.
Faz GET paginado de cada tabela crítica via PostgREST, salva JSON gzip datado e roda rotação.
SOMENTE LEITURA (não altera nada no banco).

Saída: C:\\Users\\carlo\\Backups\\supabase\\YYYY-MM-DD\\<tabela>.json.gz  + _manifest.json
Rotação: mantém os últimos RETER_DIAS diretórios datados.

Uso:
  python backup_supabase.py                 # backup completo + rotação
  SUPABASE_KEY=<chave> python backup_supabase.py   # chave por env (recomendado após rotação)
Chave: usa env SUPABASE_KEY se existir; senão a constante (fallback atual).
"""
import os, json, gzip, urllib.request, urllib.error, datetime, sys, shutil

SB   = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY  = os.environ.get("SUPABASE_KEY", "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo")
DEST = r"C:\Users\carlo\Backups\supabase"
RETER_DIAS = 30
PAGE = 1000

# Tabelas críticas (superset tolerante — as inexistentes são puladas com aviso).
TABELAS = [
    # Fórmula / laboratório
    "ativos", "ativos_sinonimos", "tipos_capsula", "formas_farmaceuticas",
    "orcamentos", "receita_logs",
    "lab_ordens", "lab_ordem_itens", "lab_formulas_padrao", "lab_formulas_padrao_itens",
    "lab_lotes_mp", "lab_producao", "lab_producao_itens",
    # Cadastros compartilhados
    "fornecedores", "prescritores", "clientes",
    # CRM
    "crm_workspaces", "crm_pipelines", "crm_estagios", "crm_cards",
    "crm_cards_historico", "crm_contatos",
    # Caixa / financeiro
    "caixa_sessoes", "caixa_movimentos", "caixa_recebiveis", "caixa_taxas",
    "caixa_vendas", "caixa_venda_itens", "caixa_pagamentos", "caixa_formas_pagamento",
    "caixa_adquirentes", "caixa_recibos", "caixa_recibo_vendas", "caixa_emitente_fiscal",
    # Entregas
    "entregas",
]

def fetch_all(tab):
    """GET paginado por Range; retorna (linhas, http_status_do_1o_erro)."""
    out, page = [], 0
    while True:
        req = urllib.request.Request(
            f"{SB}/{tab}?select=*",
            headers={"apikey": KEY, "Authorization": "Bearer " + KEY,
                     "Range-Unit": "items", "Range": f"{page*PAGE}-{page*PAGE+PAGE-1}"})
        try:
            ch = json.loads(urllib.request.urlopen(req, timeout=120).read().decode())
        except urllib.error.HTTPError as e:
            return None, e.code
        if not isinstance(ch, list):
            return None, "resp-not-list"
        out += ch
        if len(ch) < PAGE:
            return out, 200
        page += 1

def main():
    hoje = os.environ.get("BK_DATE")  # permite datar manual em teste; senão data local
    if not hoje:
        hoje = datetime.datetime.now().strftime("%Y-%m-%d")
    dia_dir = os.path.join(DEST, hoje)
    os.makedirs(dia_dir, exist_ok=True)

    manifest = {"data": hoje, "gerado_em": datetime.datetime.now().isoformat(timespec="seconds"),
                "projeto": "gclayesytzzpzkjvgede", "tabelas": {}}
    total_linhas = total_bytes = 0
    for tab in TABELAS:
        linhas, st = fetch_all(tab)
        if linhas is None:
            manifest["tabelas"][tab] = {"status": f"PULADA (HTTP {st})", "linhas": 0}
            print(f"  - {tab:<26} PULADA (HTTP {st})")
            continue
        fp = os.path.join(dia_dir, f"{tab}.json.gz")
        data = json.dumps(linhas, ensure_ascii=False).encode("utf-8")
        with gzip.open(fp, "wb") as g:
            g.write(data)
        b = os.path.getsize(fp)
        manifest["tabelas"][tab] = {"status": "ok", "linhas": len(linhas), "bytes_gz": b}
        total_linhas += len(linhas); total_bytes += b
        print(f"  + {tab:<26} {len(linhas):>7} linhas  ({b/1024:.0f} KB gz)")

    manifest["totais"] = {"linhas": total_linhas, "bytes_gz": total_bytes}
    with open(os.path.join(dia_dir, "_manifest.json"), "w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)

    # Rotação: mantém os últimos RETER_DIAS diretórios com nome de data
    dirs = sorted(d for d in os.listdir(DEST)
                  if os.path.isdir(os.path.join(DEST, d)) and len(d) == 10 and d[4] == "-")
    apagar = dirs[:-RETER_DIAS] if len(dirs) > RETER_DIAS else []
    for d in apagar:
        shutil.rmtree(os.path.join(DEST, d), ignore_errors=True)

    print(f"\nBACKUP {hoje}: {total_linhas} linhas, {total_bytes/1024/1024:.1f} MB gz -> {dia_dir}")
    print(f"Rotação: {len(dirs)-len(apagar)} dias mantidos" + (f" | {len(apagar)} apagados" if apagar else ""))
    if os.environ.get("SUPABASE_KEY"):
        print("Chave: env SUPABASE_KEY")
    else:
        print("Chave: constante embutida (mover p/ env SUPABASE_KEY após rotação da service key)")

if __name__ == "__main__":
    main()
