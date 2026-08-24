# -*- coding: utf-8 -*-
"""Seed extra da Farmácia Modelo p/ o vídeo do CRM: automações e campos
customizados (as telas não podem sair vazias). Idempotente."""
import requests, sys, json, os
sys.stdout.reconfigure(encoding="utf-8")
key = os.environ.get("SUPABASE_KEY") or open(r"C:\Users\carlo\FCertaSync\supabase_key.txt").read().strip()
h = {"apikey": key, "Authorization": "Bearer " + key, "Content-Type": "application/json",
     "Prefer": "return=representation"}
sb = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
HERE = os.path.dirname(os.path.abspath(__file__))
IDS = json.load(open(os.path.join(HERE, "demo_ids.json")))
WS, PL = IDS["workspace_id"], IDS["pipeline_vendas"]

ests = {e["nome"]: e["id"] for e in requests.get(
    sb + f"/crm_estagios?pipeline_id=eq.{PL}&select=id,nome", headers=h).json()}

AUTO = [
    ("Boas-vindas ao novo lead", ests.get("Novo Lead"), "entrar_fase", "enviar_mensagem", 0,
     "Olá, [nome]! Recebemos seu contato 💚 Em instantes um de nossos atendentes fala com você."),
    ("Cliente respondeu → Em Atendimento", ests.get("Novo Lead"), "cliente_respondeu", "mover_card", 0, None),
    ("Proposta sem resposta 24h → follow-up", ests.get("Proposta Enviada"), "tempo_na_fase", "enviar_mensagem", 1440,
     "Oi, [nome]! Conseguiu ver a proposta que enviamos? Qualquer dúvida estou por aqui 😊"),
    ("Retorno agendado → lembrete no dia", ests.get("Retorno Agendado"), "tempo_na_fase", "enviar_mensagem", 2880,
     "Bom dia, [nome]! Passando para confirmar nosso retorno combinado. Podemos falar hoje?"),
    ("Sem resposta 5 dias → encerra", ests.get("Última Tentativa") or ests.get("Negociação Final"),
     "tempo_na_fase", "mover_card", 7200, None),
]
print("== automações ==")
for i, (nome, est, tipo, acao, delay, msg) in enumerate(AUTO):
    if not est: continue
    ja = requests.get(sb + f"/crm_automacoes?workspace_id=eq.{WS}&nome=eq.{requests.utils.quote(nome)}&limit=1", headers=h).json()
    if ja: print("  =", nome); continue
    row = {"workspace_id": WS, "pipeline_id": PL, "estagio_id": est, "nome": nome,
           "tipo": tipo, "acao": acao, "delay_minutos": delay, "ordem": i, "ativo": True}
    if msg: row["mensagem"] = msg
    if acao == "mover_card":
        row["para_estagio_id"] = ests.get("Em Atendimento") if "respondeu" in nome else ests.get("Cancelado")
    r = requests.post(sb + "/crm_automacoes", headers=h, json=row)
    print("  +" if r.status_code < 300 else f"  FALHOU {r.status_code}", nome)

CAMPOS = [
    ("como_nos_conheceu", "Como nos conheceu?", "select", ["Indicação", "Instagram", "Google", "Já é cliente"]),
    ("formula_para_quanto_tempo_dias", "Fórmula para quanto tempo? (Dias)", "numero", None),
    ("numero_requisicao", "Número Requisição", "texto", None),
    ("data_prevista_retirada", "Data prevista de retirada", "data", None),
]
print("== campos customizados ==")
for chave, nome, tipo, opcoes in CAMPOS:
    ja = requests.get(sb + f"/crm_campos_definicao?workspace_id=eq.{WS}&chave=eq.{chave}&limit=1", headers=h).json()
    if ja: print("  =", nome); continue
    row = {"workspace_id": WS, "pipeline_id": PL, "chave": chave, "nome": nome, "tipo": tipo}
    if opcoes: row["opcoes"] = opcoes
    r = requests.post(sb + "/crm_campos_definicao", headers=h, json=row)
    print("  +" if r.status_code < 300 else f"  FALHOU {r.status_code} {r.text[:120]}", nome)
print("seed extra ok")
