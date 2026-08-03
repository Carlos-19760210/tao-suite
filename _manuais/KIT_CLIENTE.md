# Kit do Cliente — TAO Neo · v1 (congelado em 02/08/2026)

Conjunto de documentos de onboarding e conformidade entregues ao cliente. Versionar sempre em bloco: ao mexer em um, revisar a consistência dos outros e subir a versão do Kit.

## Componentes

| # | Documento | Papel (dono) | Público | Versão | Gerador |
|---|---|---|---|---|---|
| 1 | **Manual TAO Neo** | Operação da **jornada** (WhatsApp → card → venda → recebimento → pós-venda) | Equipe de atendimento / vendas | v11 | `_manuais/gen_op_manual.py` |
| 2 | **Manual TAO Lab** | **Produção e estoque** (cadastros → OM → pesagem → lote → controlados → rótulo) | Equipe da farmácia / RT | v7 | `tao-lab/doc_gera_manual.py` (+ `manual_cap_card.py`) |
| 3 | **Protocolo de Validação Farmacotécnica** | De-acordo técnico da RT (RDC 67) — casos de teste + assinatura | Responsável Técnica | v1 | `tao-lab/doc_gera_protocolo.py` |

Saída dos DOCX: pasta do usuário (`C:\Users\carlo\`), via variáveis de ambiente `OPMAN_OUT` / `MAN_OUT` / `PROT_OUT`.

## Convenções do Kit (aplicadas em todos)
- **Sem citar o sistema legado pelo nome** — sempre "sistema de origem".
- **Perfis padronizados:** Administrador · Gestor · Operacional ("Master" = termo técnico interno = Administrador).
- **White-label:** "provisionado pela TAO", "servidor de mensagens" (sem citar o fornecedor de WhatsApp).
- **Donos de capítulo (sem duplicar):** produção/estoque só no Lab; jornada comercial só no Neo; cada um referencia o outro.
- **Campanhas:** fora do manual (módulo não documentado no Kit v1).
- **Modularidade:** nota de "módulos conforme contrato" no go-live (módulo não contratado não aparece no menu).
- **Go-live** cobre LGPD (aviso/consentimento + anonimização), backup/retenção e contingência.

## Pendências mapeadas (avaliação externa — para o Kit v2)
- Capítulo de **Campanhas** com salvaguardas anti-banimento (só quando as proteções — janela/lote/delay/aquecimento — estiverem no fluxo).
- **Troubleshooting** dedicado (documento Técnico/Infra interno).
- Tela de **módulo bloqueado** documentada quando o licenciamento modular entrar.

## Como regerar o Kit
```
# Neo
cd _manuais && OPMAN_OUT="...Manual_TAO_CRM_Neo_v11.docx" python gen_op_manual.py
# Lab (recapturar prints antes — ver project_taolab_fechamento na memória)
cd tao-lab && MAN_PRINTS="...prints" MAN_OUT="...Manual_Usuario_TAO_Lab_v7.docx" python doc_gera_manual.py
# Protocolo
cd tao-lab && python doc_gera_protocolo.py
```
