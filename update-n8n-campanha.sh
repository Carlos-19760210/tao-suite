#!/bin/bash
N8N_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI5YThjOTBhYy01ZDBhLTQ3MmUtYmEwZi05M2RjNzUxNWViNzAiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzc1ODE4NDM1fQ.7_lmXo4vszCsw_JFFlhuMptbpaMfqw_BQQYPDT4hiX8"
N8N_BASE="https://crowingbettafish-n8n.cloudfy.live"
WF_ID="LXRzDVP6RnXGHPhR"

# Ler workflow atual
WF=$(curl -s -H "X-N8N-API-KEY: $N8N_KEY" -H "User-Agent: Mozilla/5.0" \
  "$N8N_BASE/api/v1/workflows/$WF_ID")

echo "Workflow lido: $(echo $WF | grep -o '"name":"CBPM[^"]*"')"

# Extrair nodes e connections
NODES=$(echo $WF | grep -o '"nodes":\[.*\],"connections"' | sed 's/,"connections"//')
CONNECTIONS=$(echo $WF | grep -o '"connections":{[^}]*\(}[^}]*\)*},"settings"' | sed 's/,"settings"//')
SETTINGS=$(echo $WF | grep -o '"settings":{[^}]*}' | head -1)
STATIC=$(echo $WF | grep -o '"staticData":{[^}]*}' | head -1)

echo "OK: estrutura extraída"
