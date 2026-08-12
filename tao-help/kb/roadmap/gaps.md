# Roadmap — gaps vs concorrência

*Documento interno.* Fechar os pontos onde a TEXs entrega e o TAO Neo ainda não.

## Ordem sugerida
1. **Omnichannel — Telegram** (ganho rápido, baixo risco)
2. **NFS‑e** (fiscal — destrava cidades com ISS)
3. **SNGPC transmissão** (regulatório — cautela + OK formal)
4. **Omnichannel — Instagram/Messenger** (gargalo = App Review Meta; iniciar cedo)

## Gap 1 — Omnichannel
Base: arquitetura de *provider por instância* (`messaging.php`: interface + adapters Evolution/Meta), Inbox unificado, contato único.
- **1a Telegram:** adapter Bot API (webhook + sendMessage). Sem revisão de app.
- **1b Instagram/Messenger:** estende o `Tao_Meta_Adapter` para Messenger Platform + Instagram Messaging. Exige app Meta + páginas vinculadas + **App Review**.

## Gap 2 — NFS‑e (ISS)
Base: adaptador **Focus** (NFC‑e no ar), tela Fiscal, campos fiscais no ativo.
- Definição fiscal com o contador (serviço/ISS × mercadoria/ICMS varia por município), inscrição municipal, código de serviço (LC 116), alíquota.
- Adapter Focus NFS‑e (emitir/consultar/cancelar; guardar nº/verificação/PDF/XML na venda).
- Emissão a partir da venda + homologação com o contador.

## Gap 3 — Transmissão automática SNGPC → ANVISA
Base: SNGPC decodificado, staging, transmissão user/senha, **estratégia SOMBRA validada** (100% match em jul).
- Sair da sombra: transmitir de fato (mecanismo já decodificado) com credenciais da farmácia.
- Rotina + retorno/erro/reprocesso + auditoria.
- Convivência sombra+real até bater 100% → virar a chave. **Risco regulatório alto → OK formal + RT.**

## Macro‑cronograma
- **S1:** Telegram + abrir NFS‑e (contador) + iniciar App Review Meta.
- **S2:** NFS‑e (adapter + emissão + homologação).
- **S3:** SNGPC em convivência.
- **S4:** Instagram/Messenger (após aprovação Meta).

*Contas a pagar já existe (Financeiro › Contas a Pagar, via Fórmula) — saiu da lista de gaps.*
