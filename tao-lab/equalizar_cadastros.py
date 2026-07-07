#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
EQUALIZAÇÃO DE CADASTROS — pipeline recorrente do TAO Lab (validação até virar a chave).
Roda, EM ORDEM, as rotinas idempotentes que espelham o FCerta na base do TAO:

  1. sync_historico.py          — clientes + fórmulas novos do FCerta (gera work.ib)
  2. sync_historico --backfill-extra — tpcap/prescritor nas fórmulas já carregadas
  3. juncao_contatos_fcerta.py  — vincula/cria contato ÚNICO do CRM por celular
  4. enriquece_contatos_fcerta.py — completa doc(CPF)/sexo/endereço/etc (só campos vazios)

Todas idempotentes: seguro rodar quantas vezes quiser. Uma etapa por vez (sem
paralelismo — evita corrida). Basta atualizar o backup do FCerta e rodar isto.

Uso: python equalizar_cadastros.py                 # pipeline completo
     python equalizar_cadastros.py --só juncao,enriquece
     python equalizar_cadastros.py --db-dir "D:\\backups\\fcerta"
"""
import subprocess, sys, os, argparse, time

AQUI = os.path.dirname(os.path.abspath(__file__))

ETAPAS = [
    ("sync",      ["sync_historico.py"]),
    ("backfill",  ["sync_historico.py", "--backfill-extra"]),
    ("juncao",    ["juncao_contatos_fcerta.py"]),
    ("enriquece", ["enriquece_contatos_fcerta.py"]),
]

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--só", "--so", dest="so", default="", help="etapas separadas por vírgula (sync,backfill,juncao,enriquece)")
    ap.add_argument("--db-dir",  default=None, help="repassado ao sync_historico")
    ap.add_argument("--db-file", default=None)
    args = ap.parse_args()

    alvo = [e.strip() for e in args.so.split(",") if e.strip()] if args.so else [n for n, _ in ETAPAS]
    print("=" * 60)
    print("EQUALIZAÇÃO DE CADASTROS — TAO Lab")
    print("etapas:", ", ".join(alvo))
    print("=" * 60)

    for nome, cmd in ETAPAS:
        if nome not in alvo: continue
        full = [sys.executable, "-u", os.path.join(AQUI, cmd[0])] + cmd[1:]
        if nome in ("sync", "backfill"):
            if args.db_dir:  full += ["--db-dir", args.db_dir]
            if args.db_file: full += ["--db-file", args.db_file]
        print(f"\n{'─'*60}\n▶ ETAPA: {nome}  ({' '.join(cmd)})\n{'─'*60}")
        env = dict(os.environ, PYTHONIOENCODING="utf-8")
        r = subprocess.run(full, env=env)
        if r.returncode != 0:
            print(f"\n✖ ETAPA '{nome}' falhou (exit {r.returncode}). Pipeline interrompido.")
            sys.exit(r.returncode)
        time.sleep(1)

    print("\n" + "=" * 60)
    print("✔ EQUALIZAÇÃO CONCLUÍDA — cadastros do TAO espelhando o FCerta")
    print("=" * 60)

if __name__ == "__main__":
    main()
