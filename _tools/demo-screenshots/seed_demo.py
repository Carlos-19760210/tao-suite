# -*- coding: utf-8 -*-
"""
SEED "Farmácia Modelo" — negócio demo isolado p/ screenshots comerciais do TAO Neo.

- 100%% dados fictícios (LGPD): nomes *Demo/Exemplo/Modelo*, telefones 5511 96000-00XX.
- Idempotente: âncora = clientes.instancia_whats == 'farmacia-modelo-demo'.
  Cada entidade é buscada por chave natural antes de inserir; rodar 2x não duplica.
- NÃO toca nenhum negócio existente: só insere linhas com o cliente_id/workspace_id novos
  e wp_options/user novos namespaceados pelo workspace demo.
- Usuário WP "gestor.demo" (role cbpm_gestor) criado via wp-cli por SSH.

Uso:  python seed_demo.py            (seed completo)
      python seed_demo.py --no-wp    (só Supabase, pula usuário/options WP)
Saída: demo_ids.json (uuids) e demo_credentials.json (login do gestor — NÃO commitar).
"""
import json, os, secrets, subprocess, sys
from datetime import datetime, timedelta, timezone

sys.stdout.reconfigure(encoding="utf-8")

SB = "https://gclayesytzzpzkjvgede.supabase.co/rest/v1"
KEY = os.environ.get("SUPABASE_KEY") or open(r"C:\Users\carlo\FCertaSync\supabase_key.txt").read().strip()
HDR = {"apikey": KEY, "Authorization": f"Bearer {KEY}", "Content-Type": "application/json",
       "Prefer": "return=representation"}
SSH = ["ssh", "-i", r"C:\Users\carlo\.ssh\tao_crm_deploy", "-p", "65002",
       "u178063243@solucoesetao.com.br"]
WP_PATH = "domains/solucoesetao.com.br/public_html"
HERE = os.path.dirname(os.path.abspath(__file__))
TZ = timezone(timedelta(hours=-3))  # America/Sao_Paulo
HOJE = datetime.now(TZ)

import requests

def die(msg):
    print(f"ERRO: {msg}"); sys.exit(1)

def get(path):
    r = requests.get(f"{SB}/{path}", headers=HDR, timeout=30)
    if r.status_code >= 400: die(f"GET {path} -> {r.status_code} {r.text[:300]}")
    return r.json()

def post(table, payload):
    r = requests.post(f"{SB}/{table}", headers=HDR, json=payload, timeout=30)
    if r.status_code >= 400: die(f"POST {table} -> {r.status_code} {r.text[:300]}")
    return r.json()

def patch(path, payload):
    r = requests.patch(f"{SB}/{path}", headers=HDR, json=payload, timeout=30)
    if r.status_code >= 400: die(f"PATCH {path} -> {r.status_code} {r.text[:300]}")
    return r.json()

def find_or_create(table, filtro, payload, label):
    rows = get(f"{table}?{filtro}&limit=1")
    if rows:
        print(f"  = {label} (já existe)")
        return rows[0]
    row = post(table, payload)[0]
    print(f"  + {label}")
    return row

def wp(args):
    cmd = SSH + [f"cd {WP_PATH} && wp " + args]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=90, encoding="utf-8")
    if r.returncode != 0:
        die(f"wp {args} -> {r.stderr.strip()[:300]}")
    return r.stdout.replace("﻿", "").strip()

def hoje_ts(h, m):
    return HOJE.replace(hour=h, minute=m, second=0, microsecond=0).isoformat()

# ---------------------------------------------------------------- 1. CLIENTE
print("== 1/9 Cliente (negócio) ==")
SYSTEM_PROMPT = (
    "Você é a Bia, atendente virtual da Farmácia Modelo.\n\n"
    "PERSONALIDADE: acolhedora, objetiva e confiável. Trate o cliente pelo primeiro nome. "
    "Use no máximo 1 emoji por mensagem.\n\n"
    "O QUE VOCÊ FAZ:\n"
    "- Informa preço e disponibilidade dos produtos do catálogo\n"
    "- Tira dúvidas simples de uso (posologia da embalagem)\n"
    "- Anota o pedido e encaminha para a equipe confirmar\n\n"
    "REGRAS:\n"
    "- NUNCA recomende medicamento tarja ou substitua orientação do farmacêutico\n"
    "- Dúvida clínica, receita ou reclamação: transfira para atendimento humano\n"
    "- Preço só do catálogo oficial; se não souber, diga que vai confirmar\n"
    "- Fora do horário (seg–sáb 8h–20h), informe que a equipe responde na abertura"
)
cliente = find_or_create(
    "clientes", "instancia_whats=eq.farmacia-modelo-demo",
    {"nome_negocio": "Farmácia Modelo", "instancia_whats": "farmacia-modelo-demo",
     "numeros_ignorados": [], "tipo_negocio": "farmacia", "modulo_saida": "lead",
     "nome_persona": "Bia", "system_prompt": SYSTEM_PROMPT,
     "apresentacao": "Oi! Eu sou a Bia, assistente da Farmácia Modelo 💚 Posso te passar preços, ver disponibilidade e anotar seu pedido. Como posso ajudar?",
     "handoff_keywords": ["humano", "atendente", "farmacêutico", "farmaceutico"],
     "criterio_lead_quente": "Cliente pediu preço de 2+ produtos ou pediu para reservar/comprar",
     "criterio_lead_frio": "Só cumprimentou ou perguntou endereço/horário",
     "ativo": True, "tem_neo": True, "tem_formula": False},
    "clientes: Farmácia Modelo")
CID = cliente["id"]

ws = find_or_create("crm_workspaces", f"cliente_id=eq.{CID}",
                    {"nome": "Farmácia Modelo", "cliente_id": CID, "ativo": True},
                    "crm_workspaces: Farmácia Modelo")
WS = ws["id"]

# ------------------------------------------------------------- 2. CATÁLOGO
print("== 2/9 Catálogo ==")
CATS = ["Vitaminas e Suplementos", "Bem-estar e Sono", "Beleza e Pele"]
for i, c in enumerate(CATS):
    find_or_create("categorias", f"cliente_id=eq.{CID}&nome=eq.{requests.utils.quote(c)}",
                   {"cliente_id": CID, "nome": c, "ordem": i}, f"categoria: {c}")

CAMPOS = [
    ("principio_ativo", "Princípio ativo", "texto", 0),
    ("dosagem", "Dosagem", "texto", 1),
    ("indicacao", "Indicação de uso", "textarea", 2),
    ("uso_continuo", "Uso contínuo", "booleano", 3),
]
for chave, label, tipo, ordem in CAMPOS:
    find_or_create("campos_extras", f"cliente_id=eq.{CID}&chave=eq.{chave}",
                   {"cliente_id": CID, "chave": chave, "label": label,
                    "tipo_campo": tipo, "ordem": ordem, "obrigatorio": False},
                   f"campo extra: {label}")

PRODUTOS = [
    ("Vitamina D3 10.000 UI — 60 cápsulas", CATS[0], 79.90, "Colecalciferol", "10.000 UI",
     "Imunidade e saúde óssea. 1 cápsula ao dia, com refeição.", True),
    ("Magnésio Dimalato 700mg — 120 cápsulas", CATS[0], 89.90, "Magnésio dimalato", "700 mg",
     "Disposição e função muscular. 2 cápsulas ao dia.", True),
    ("Ômega 3 1000mg EPA/DHA — 120 cápsulas", CATS[0], 119.90, "Óleo de peixe purificado", "1.000 mg",
     "Saúde cardiovascular. 2 cápsulas ao dia.", True),
    ("Vitamina C 1g Efervescente — 30 comprimidos", CATS[0], 34.90, "Ácido ascórbico", "1 g",
     "Imunidade. 1 comprimido ao dia dissolvido em água.", False),
    ("Complexo B — 60 cápsulas", CATS[0], 49.90, "Vitaminas B1, B6 e B12", "—",
     "Energia e sistema nervoso. 1 cápsula ao dia.", True),
    ("Zinco Quelato 29,59mg — 60 cápsulas", CATS[0], 44.90, "Zinco bisglicinato", "29,59 mg",
     "Imunidade e pele. 1 cápsula ao dia.", True),
    ("Melatonina 0,21mg — 60 comprimidos", CATS[1], 39.90, "Melatonina", "0,21 mg",
     "Auxílio ao sono. 1 comprimido 30 min antes de deitar.", False),
    ("Probiótico 10 bilhões UFC — 30 cápsulas", CATS[1], 129.90, "Lactobacillus + Bifidobacterium", "10 bi UFC",
     "Equilíbrio intestinal. 1 cápsula em jejum.", True),
    ("Colágeno Tipo 2 40mg — 30 cápsulas", CATS[2], 99.90, "Colágeno tipo II não desnaturado", "40 mg",
     "Conforto articular. 1 cápsula ao dia.", True),
    ("Sérum de Ácido Hialurônico — 30ml", CATS[2], 149.90, "Ácido hialurônico 1%", "—",
     "Hidratação facial. Aplicar manhã e noite na pele limpa.", False),
]
for i, (nome, cat, preco, pa, dose, ind, cont) in enumerate(PRODUTOS):
    find_or_create("catalogo", f"cliente_id=eq.{CID}&nome=eq.{requests.utils.quote(nome)}",
                   {"cliente_id": CID, "nome": nome, "categoria": cat, "preco": preco,
                    "descricao": ind, "imagens": [], "destaque": {},
                    "divulga_preco": True, "disponivel": True, "ordem": i,
                    "extras": {"principio_ativo": pa, "dosagem": dose,
                               "indicacao": ind, "uso_continuo": cont}},
                   f"produto: {nome[:40]}")

# ------------------------------------------------------------------ 3. CRM
print("== 3/9 CRM: funil, contatos, cards ==")
pipe = find_or_create("crm_pipelines", f"workspace_id=eq.{WS}&nome=eq.Vendas",
                      {"workspace_id": WS, "nome": "Vendas", "ordem": 0, "ativo": True},
                      "pipeline: Vendas")
PL = pipe["id"]

ESTAGIOS = [  # template farmácia do settings.php
    ("Novo Lead", "#3b82f6", "normal"), ("Em Atendimento", "#f59e0b", "normal"),
    ("Proposta Enviada", "#8b5cf6", "normal"), ("Análise Técnica", "#06b6d4", "normal"),
    ("Retorno Agendado", "#f97316", "normal"), ("Negociação Final", "#ec4899", "normal"),
    ("Venda Concluída", "#10b981", "ganho"), ("Cancelado", "#ef4444", "perdido"),
]
EST = {}
for i, (nome, cor, tipo) in enumerate(ESTAGIOS):
    e = find_or_create("crm_estagios",
                       f"pipeline_id=eq.{PL}&nome=eq.{requests.utils.quote(nome)}",
                       {"pipeline_id": PL, "nome": nome, "cor": cor, "tipo": tipo, "ordem": i},
                       f"estágio: {nome}")
    EST[nome] = e["id"]

# (nome, fone, estágio, valor, hora_criação)
CARDS = [
    ("Maria Exemplo",  "5511960000001", "Em Atendimento",  169.80, (9, 12)),
    ("João Demo",      "5511960000002", "Proposta Enviada", 89.90, (9, 47)),
    ("Ana Modelo",     "5511960000003", "Novo Lead",         0.00, (10, 5)),
    ("Pedro Fictício", "5511960000004", "Novo Lead",       119.90, (10, 22)),
    ("Carla Amostra",  "5511960000005", "Em Atendimento",  249.70, (10, 40)),
    ("Rafael Teste",   "5511960000006", "Análise Técnica", 129.90, (11, 2)),
    ("Juliana Demo",   "5511960000007", "Retorno Agendado", 79.90, (11, 30)),
    ("Marcos Exemplo", "5511960000008", "Negociação Final", 314.60, (11, 55)),
    ("Beatriz Modelo", "5511960000009", "Venda Concluída",  184.80, (12, 15)),
]
card_ids = {}
for nome, fone, est, valor, (h, m) in CARDS:
    ct = find_or_create("crm_contatos", f"workspace_id=eq.{WS}&whatsapp=eq.{fone}",
                        {"workspace_id": WS, "whatsapp": fone, "nome": nome, "tags": []},
                        f"contato: {nome}")
    cd = find_or_create("crm_cards",
                        f"workspace_id=eq.{WS}&contato_whatsapp=eq.{fone}&pipeline_id=eq.{PL}",
                        {"workspace_id": WS, "pipeline_id": PL, "estagio_id": EST[est],
                         "contato_id": ct["id"], "titulo": nome, "contato_nome": nome,
                         "contato_whatsapp": fone, "valor_oportunidade": valor,
                         "status": "ganho" if est == "Venda Concluída" else "aberto",
                         "fechado": est == "Venda Concluída",
                         "criado_em": hoje_ts(h, m), "movido_em": hoje_ts(h, m)},
                        f"card: {nome} [{est}]")
    card_ids[nome] = cd["id"]

# ------------------------------------------------------- 4. CONVERSAS (2 cards)
print("== 4/9 Conversas nos cards ==")
def conversa(card_nome, msgs):
    cid_card = card_ids[card_nome]
    last = (HOJE - timedelta(minutes=msgs[-1][0])).isoformat()
    if get(f"crm_mensagens?card_id=eq.{cid_card}&limit=1"):
        print(f"  = conversa de {card_nome} (já existe)")
    else:
        payload = []
        for (mins_ago, direcao, autor, texto) in msgs:
            ts = (HOJE - timedelta(minutes=mins_ago)).isoformat()
            payload.append({"card_id": cid_card, "workspace_id": WS, "direcao": direcao,
                            "tipo": "text", "conteudo": texto, "remetente_nome": autor,
                            "enviado_em": ts})
        post("crm_mensagens", payload)
        print(f"  + conversa de {card_nome} ({len(msgs)} msgs)")
    patch(f"crm_cards?id=eq.{cid_card}", {"ultima_mensagem_em": last})

conversa("Maria Exemplo", [
    (95, "in",  "Maria Exemplo", "Oi, bom dia! Vocês têm vitamina D de 10 mil?"),
    (94, "out", "Bia", "Bom dia, Maria! 💚 Temos sim: Vitamina D3 10.000 UI com 60 cápsulas por R$ 79,90. Quer que eu separe uma pra você?"),
    (92, "in",  "Maria Exemplo", "Tem magnésio também? O dimalato"),
    (91, "out", "Bia", "Temos! Magnésio Dimalato 700mg com 120 cápsulas: R$ 89,90. Levando os dois, o total fica R$ 169,80."),
    (89, "in",  "Maria Exemplo", "Perfeito, pode separar os dois então"),
    (88, "out", "Bia", "Combinado! Já anotei: D3 10.000 UI + Magnésio Dimalato = R$ 169,80. Vou passar para a equipe confirmar a retirada, tá bom?"),
    (86, "in",  "Maria Exemplo", "Consigo retirar hoje à tarde? Queria falar com um atendente"),
    (85, "out", "Equipe Farmácia Modelo", "Oi Maria, aqui é o Gestor da Farmácia Modelo 😊 Consegue sim — seu pedido fica separado no balcão até as 20h. Qualquer coisa me chama por aqui!"),
])
patch(f"crm_cards?id=eq.{card_ids['Maria Exemplo']}", {"atendimento_humano": True})

conversa("João Demo", [
    (60, "in",  "João Demo", "Boa tarde! Quanto tá o magnésio dimalato de vocês?"),
    (59, "out", "Bia", "Boa tarde, João! O Magnésio Dimalato 700mg (120 cápsulas) está R$ 89,90. Posso reservar?"),
    (57, "in",  "João Demo", "Esse rende quanto tempo de uso?"),
    (56, "out", "Bia", "Tomando 2 cápsulas ao dia, o pote rende 2 meses de uso 😊"),
    (54, "in",  "João Demo", "Boa! Reserva um pra mim então"),
    (53, "out", "Bia", "Reservado! Fica separado no seu nome até amanhã às 20h. Precisa de mais alguma coisa?"),
    (51, "in",  "João Demo", "Só isso, obrigado!"),
    (50, "out", "Bia", "Nós que agradecemos, João! Qualquer dúvida é só chamar 💚"),
])

# ---------------------------------------------------- 5. HISTÓRICO DO AGENTE
print("== 5/9 Histórico do agente (tabela historico) ==")
if not get(f"historico?cliente_id=eq.{CID}&limit=1"):
    rows = []
    conversas_hist = [
        ("5511960000001", "Maria Exemplo", 95, [
            ("user", "Oi, bom dia! Vocês têm vitamina D de 10 mil?"),
            ("assistant", "Bom dia, Maria! 💚 Temos sim: Vitamina D3 10.000 UI com 60 cápsulas por R$ 79,90."),
            ("user", "Tem magnésio também? O dimalato"),
            ("assistant", "Temos! Magnésio Dimalato 700mg com 120 cápsulas: R$ 89,90."),
        ]),
        ("5511960000002", "João Demo", 60, [
            ("user", "Boa tarde! Quanto tá o magnésio dimalato de vocês?"),
            ("assistant", "Boa tarde, João! O Magnésio Dimalato 700mg (120 cápsulas) está R$ 89,90. Posso reservar?"),
        ]),
        ("5511960000005", "Carla Amostra", 45, [
            ("user", "Oi! Tem colágeno tipo 2?"),
            ("assistant", "Oi, Carla! Temos o Colágeno Tipo 2 40mg (30 cápsulas) por R$ 99,90 😊"),
            ("user", "E o sérum de ácido hialurônico?"),
            ("assistant", "Também! Sérum de Ácido Hialurônico 30ml por R$ 149,90. Os dois juntos: R$ 249,70."),
        ]),
        ("5511960000007", "Juliana Demo", 30, [
            ("user", "Vocês abrem no sábado?"),
            ("assistant", "Abrimos sim, Juliana! Sábado das 8h às 20h. Posso ajudar com mais alguma coisa?"),
        ]),
    ]
    for fone, nome, base_min, msgs in conversas_hist:
        for i, (role, txt) in enumerate(msgs):
            rows.append({"cliente_id": CID, "phone": fone, "nome_contato": nome,
                         "role": role, "conteudo": txt,
                         "criado_em": (HOJE - timedelta(minutes=base_min - i)).isoformat()})
    post("historico", rows)
    print(f"  + {len(rows)} mensagens de histórico")
else:
    print("  = histórico (já existe)")

# ----------------------------------------------------------- 6. CAMPANHA
print("== 6/9 Campanha 'Semana da Imunidade' ==")
camp = find_or_create(
    "campanhas", f"cliente_id=eq.{CID}&nome=eq.{requests.utils.quote('Semana da Imunidade')}",
    {"cliente_id": CID, "nome": "Semana da Imunidade",
     "cabecalho": "Olá, [nome]! Aqui é a Bia, da [negocio] 💚",
     "variantes": [
         "Essa semana é a Semana da Imunidade: Vitamina D3, Vitamina C e Zinco com 15% de desconto até sábado. Quer que eu separe o seu kit?",
         "Montamos kits de imunidade com 15% off até sábado (D3 + Vitamina C + Zinco). Posso reservar um no seu nome?"],
     "intervalo_min": 45, "intervalo_max": 180,
     "horario_inicio": "08:00", "horario_fim": "20:00", "lote_max_dia": 80,
     "status": "em_andamento", "total_contatos": 40, "enviados": 25, "falhas": 3,
     "iniciado_em": hoje_ts(9, 0)},
    "campanha: Semana da Imunidade")
CP = camp["id"]

NOMES_CAMP = ["Antônio", "Bruna", "Caio", "Daniela", "Eduardo", "Fernanda", "Gustavo",
              "Helena", "Igor", "Jéssica", "Kleber", "Larissa", "Murilo", "Natália",
              "Otávio", "Patrícia", "Quésia", "Rodrigo", "Sabrina", "Thiago", "Úrsula",
              "Vinícius", "Wanda", "Xavier", "Yasmin", "Zeca", "Alice", "Bento",
              "Clara", "Davi", "Elisa", "Fábio", "Gabriela", "Heitor", "Íris",
              "João Pedro", "Karen", "Léo", "Mariana", "Nina"]
if not get(f"campanha_contatos?campanha_id=eq.{CP}&limit=1"):
    rows = []
    for i, nome in enumerate(NOMES_CAMP):
        fone = f"55119601{i:04d}"
        row = {"campanha_id": CP, "nome": f"{nome} Demo", "whatsapp": fone,
               "status": "pendente", "variante_idx": None, "mensagem_enviada": None,
               "enviado_em": None, "erro": None}
        if i < 25:  # enviados
            vi = i % 2
            row.update(status="enviado", variante_idx=vi,
                       mensagem_enviada=f"Olá, {nome}! Aqui é a Bia, da Farmácia Modelo 💚\n" + camp["variantes"][vi],
                       enviado_em=(HOJE.replace(hour=9, minute=5) + timedelta(minutes=i * 4)).isoformat())
        elif i >= 37:  # falhas
            row.update(status="falha", erro="Número não possui WhatsApp")
        rows.append(row)
    post("campanha_contatos", rows)
    print(f"  + 40 destinatários (25 enviados / 12 pendentes / 3 falhas)")
else:
    print("  = destinatários (já existem)")

# -------------------------------------------------------------- 7. CAIXA
print("== 7/9 Caixa: formas, sessão, 12 vendas de hoje ==")
FORMAS = [
    ("Dinheiro", "dinheiro", "dinheiro", True, 0, 0),
    ("PIX", "pix", "pix", False, 0, 0),
    ("Cartão Débito", "debito", "maquina", False, 1.99, 1),
    ("Cartão Crédito", "credito", "maquina", False, 3.49, 30),
]
forma_ids = {}
for i, (nome, tipo, canal, cnd, taxa, prazo) in enumerate(FORMAS):
    f = find_or_create("caixa_formas_pagamento",
                       f"cliente_id=eq.{CID}&nome=eq.{requests.utils.quote(nome)}",
                       {"cliente_id": CID, "nome": nome, "tipo": tipo, "canal": canal,
                        "conta_no_dinheiro": cnd, "taxa_pct": taxa,
                        "prazo_recebimento_dias": prazo, "ativo": True, "ordem": i},
                       f"forma: {nome}")
    forma_ids[nome] = f["id"]

sess = find_or_create("caixa_sessoes", f"cliente_id=eq.{CID}&status=eq.aberta",
                      {"cliente_id": CID, "operador_id": 0, "saldo_inicial": 200.00,
                       "status": "aberta", "aberto_em": hoje_ts(8, 3)},
                      "sessão de caixa aberta")
SS = sess["id"]

pipe_pos = find_or_create("crm_pipelines", f"workspace_id=eq.{WS}&nome=eq.{requests.utils.quote('Pós Vendas')}",
                          {"workspace_id": WS, "nome": "Pós Vendas", "ordem": 1, "ativo": True},
                          "pipeline: Pós Vendas")
PLPOS = pipe_pos["id"]
est_pos = find_or_create("crm_estagios", f"pipeline_id=eq.{PLPOS}&nome=eq.{requests.utils.quote('Vendas Balcão')}",
                         {"pipeline_id": PLPOS, "nome": "Vendas Balcão",
                          "cor": "#10b981", "tipo": "normal", "ordem": 0},
                         "estágio: Vendas Balcão")

# (cliente, hora, itens[(descricao, qtd, unit)], forma)
VENDAS = [
    ("Cliente Balcão 01", (8, 41), [("Vitamina C 1g Efervescente — 30 comprimidos", 1, 34.90)], "Dinheiro"),
    ("Cliente Balcão 02", (9, 5),  [("Melatonina 0,21mg — 60 comprimidos", 1, 39.90)], "PIX"),
    ("Cliente Balcão 03", (9, 32), [("Vitamina D3 10.000 UI — 60 cápsulas", 1, 79.90)], "Cartão Crédito"),
    ("Cliente Balcão 04", (10, 1), [("Magnésio Dimalato 700mg — 120 cápsulas", 1, 89.90),
                                     ("Zinco Quelato 29,59mg — 60 cápsulas", 1, 44.90)], "PIX"),
    ("Cliente Balcão 05", (10, 28), [("Complexo B — 60 cápsulas", 1, 49.90)], "Dinheiro"),
    ("Cliente Balcão 06", (11, 3), [("Ômega 3 1000mg EPA/DHA — 120 cápsulas", 1, 119.90)], "Cartão Débito"),
    ("Cliente Balcão 07", (11, 36), [("Probiótico 10 bilhões UFC — 30 cápsulas", 1, 129.90)], "Cartão Crédito"),
    ("Cliente Balcão 08", (12, 10), [("Sérum de Ácido Hialurônico — 30ml", 1, 149.90)], "PIX"),
    ("Cliente Balcão 09", (12, 44), [("Colágeno Tipo 2 40mg — 30 cápsulas", 1, 99.90),
                                      ("Vitamina C 1g Efervescente — 30 comprimidos", 1, 34.90)], "Cartão Crédito"),
    ("Cliente Balcão 10", (13, 12), [("Vitamina D3 10.000 UI — 60 cápsulas", 2, 79.90)], "PIX"),
    ("Cliente Balcão 11", (13, 40), [("Melatonina 0,21mg — 60 comprimidos", 1, 39.90),
                                      ("Magnésio Dimalato 700mg — 120 cápsulas", 1, 89.90)], "Dinheiro"),
    ("Cliente Balcão 12", (14, 8), [("Zinco Quelato 29,59mg — 60 cápsulas", 1, 44.90)], "Cartão Débito"),
]
for nome, (h, m), itens, forma_nome in VENDAS:
        if get(f"caixa_vendas?cliente_id=eq.{CID}&cliente_nome=eq.{requests.utils.quote(nome)}&status=eq.quitada&limit=1"):
            print(f"  = venda {nome} (já existe)")
            continue
        total = round(sum(q * u for _, q, u in itens), 2)
        ts = hoje_ts(h, m)
        cd = post("crm_cards", {"workspace_id": WS, "pipeline_id": PLPOS,
                                "estagio_id": est_pos["id"], "titulo": f"Balcão — {nome}",
                                "contato_nome": nome, "valor_oportunidade": total,
                                "status": "ganho", "fechado": True,
                                "criado_em": ts, "movido_em": ts})[0]
        vd = post("caixa_vendas", {"cliente_id": CID, "card_id": cd["id"], "origem": "avulsa",
                                   "cliente_nome": nome, "valor_total": total, "valor_pago": total,
                                   "status": "quitada", "criado_por": 0,
                                   "criado_em": ts, "atualizado_em": ts})[0]
        post("caixa_venda_itens", [{"cliente_id": CID, "venda_id": vd["id"], "descricao": d,
                                    "quantidade": q, "valor_unitario": u,
                                    "valor_total": round(q * u, 2)} for d, q, u in itens])
        rc = post("caixa_recibos", {"cliente_id": CID, "sessao_id": SS, "valor_total": total,
                                    "valor_pago": total, "status": "quitado",
                                    "pagador_nome": nome, "data_pagamento": HOJE.date().isoformat(),
                                    "desconto": 0, "cupom_fiscal": False, "criado_por": 0,
                                    "criado_em": ts})[0]
        forma = next(x for x in FORMAS if x[0] == forma_nome)
        taxa_pct = forma[4]
        vtaxa = round(total * taxa_pct / 100, 2)
        post("caixa_pagamentos", {"cliente_id": CID, "recibo_id": rc["id"],
                                  "forma_pagamento_id": forma_ids[forma_nome],
                                  "modalidade": forma[1] if forma[1] in ("debito", "credito") else None,
                                  "parcelas": 1,
                                  "valor_bruto": total, "taxa_pct_aplicada": taxa_pct,
                                  "valor_taxa": vtaxa, "valor_liquido": round(total - vtaxa, 2),
                                  "data_prevista_receb": (HOJE + timedelta(days=forma[5])).date().isoformat(),
                                  "estornado": False, "criado_por": 0, "criado_em": ts})
        post("caixa_recibo_vendas", {"cliente_id": CID, "recibo_id": rc["id"],
                                     "venda_id": vd["id"], "valor_aplicado": total})
        print(f"  + venda {nome} R$ {total:.2f} ({forma_nome})")

# ----------------------------------------------- 8. USUÁRIO WP + RBAC (perfil Gestor)
NO_WP = "--no-wp" in sys.argv
UID = None
if NO_WP:
    print("== 8/9 (pulado --no-wp) ==")
else:
    print("== 8/9 Usuário WP gestor.demo + perfil Gestor ==")
    existente = wp("user list --login=gestor.demo --field=ID")
    existente = "".join(ch for ch in existente if ch.isdigit())
    cred_path = os.path.join(HERE, "demo_credentials.json")
    if existente:
        UID = int(existente)
        print(f"  = usuário gestor.demo (ID {UID})")
        if os.path.exists(cred_path):
            senha = json.load(open(cred_path))["senha"]
        else:  # usuário existe mas senha desconhecida -> reseta
            senha = "Demo-" + secrets.token_urlsafe(12)
            wp(f"user update {UID} --user_pass='{senha}' --skip-email")
            print("  + senha resetada")
    else:
        senha = "Demo-" + secrets.token_urlsafe(12)
        out = wp(f"user create gestor.demo gestor.demo@farmaciamodelo.exemplo "
                 f"--role=cbpm_gestor --display_name='Gestor Farmácia Modelo' "
                 f"--user_pass='{senha}' --porcelain")
        UID = int("".join(ch for ch in out if ch.isdigit()))
        print(f"  + usuário gestor.demo (ID {UID})")
    if senha:
        json.dump({"usuario": "gestor.demo", "email": "gestor.demo@farmaciamodelo.exemplo",
                   "senha": senha,
                   "login_url": "https://solucoesetao.com.br/wp-login.php"},
                  open(cred_path, "w"), indent=2)
    wp(f"user meta update {UID} cbpm_cliente_id '{CID}'")
    wp(f"user meta update {UID} tao_crm_negocio_ativo '{WS}'")
    wp(f"option update tao_crm_gestores_ws_{WS} '[{UID}]' --format=json")
    wp(f"option update tao_crm_pos_vendas_pipeline_{WS} '{PLPOS}'")
    print("  + metas e options do workspace demo")

    perfil = find_or_create("crm_perfis", f"workspace_id=eq.{WS}&nome=eq.Gestor",
                            {"workspace_id": WS, "nome": "Gestor",
                             "descricao": "Perfil demo (screenshots)", "ativo": True},
                            "perfil: Gestor")
    PF = perfil["id"]
    TELAS_OPERA = ["neo", "neo_pedidos", "neo_leads", "neo_historico", "neo_conteudo",
                   "kanban", "contatos", "campanhas", "cadastros", "caixa", "caixa_config",
                   "crm_config", "entregas", "contas_pagar", "cotacoes"]
    TELAS_OCULTO = ["formula", "formula_estoque", "formula_prod", "formula_sngpc", "formula_config"]
    for tela in TELAS_OPERA:
        find_or_create("crm_permissoes", f"perfil_id=eq.{PF}&tela=eq.{tela}",
                       {"perfil_id": PF, "tela": tela, "recurso": "*", "permissao": "opera"},
                       f"permissão {tela}=opera")
    for tela in TELAS_OCULTO:
        find_or_create("crm_permissoes", f"perfil_id=eq.{PF}&tela=eq.{tela}",
                       {"perfil_id": PF, "tela": tela, "recurso": "*", "permissao": "oculto"},
                       f"permissão {tela}=oculto")
    find_or_create("crm_permissoes", f"perfil_id=eq.{PF}&tela=eq.plataforma_config",
                   {"perfil_id": PF, "tela": "plataforma_config", "recurso": "*",
                    "permissao": "opera"}, "permissão plataforma_config=opera")
    find_or_create("crm_perfil_usuarios", f"workspace_id=eq.{WS}&usuario_id=eq.{UID}",
                   {"workspace_id": WS, "perfil_id": PF, "usuario_id": UID},
                   "vínculo gestor.demo↔perfil Gestor")

    # sessão/vendas/responsável apontando para o usuário real
    patch(f"caixa_sessoes?id=eq.{SS}", {"operador_id": UID})
    patch(f"caixa_vendas?cliente_id=eq.{CID}&criado_por=eq.0", {"criado_por": UID})
    patch(f"caixa_recibos?cliente_id=eq.{CID}&criado_por=eq.0", {"criado_por": UID})
    patch(f"crm_cards?workspace_id=eq.{WS}&responsavel_id=is.null", {"responsavel_id": UID})

# ------------------------------------------------------------------ 9. SAÍDA
print("== 9/9 Gravando demo_ids.json ==")
json.dump({"cliente_id": CID, "workspace_id": WS, "pipeline_vendas": PL,
           "pipeline_pos": PLPOS, "sessao_caixa": SS, "campanha": CP,
           "wp_user_id": UID,
           "card_conversa_1": card_ids.get("Maria Exemplo"),
           "card_conversa_2": card_ids.get("João Demo")},
          open(os.path.join(HERE, "demo_ids.json"), "w"), indent=2)
print("\nSEED COMPLETO ✔  (re-executável; nada de negócios existentes foi tocado)")
