# ============================================================
# sincronizar_tao.ps1
# Equaliza materias-primas: Firebird (Formula Certa) -> Supabase (TAO Suite)
# Uso: .\sincronizar_tao.ps1
# ============================================================

$DESKTOP      = [Environment]::GetFolderPath('Desktop')
$FB25         = "$DESKTOP\MagisTAO\Fcerta\Fcerta\FB\FB64\25\64\isql.exe"
$DB           = "$DESKTOP\MagisTAO\Fcerta\Fcerta\DB\ALTERDB.ib"
$SUPABASE_URL = "https://gclayesytzzpzkjvgede.supabase.co"
$SUPABASE_KEY = "sb_secret_HpoqM6ujk2yD6la7KM3cuQ_pdWBK8jo"
$CLIENTE_ID   = "62f98634-77ff-42f4-acaf-8561d56583da"
$SQL_TMP      = "$env:TEMP\tao_sync_mp.sql"

Write-Host ""
Write-Host "=== TAO Formulas -- Equalizacao Formula Certa -> Supabase ===" -ForegroundColor Cyan
Write-Host "Banco  : $DB"
Write-Host "Cliente: $CLIENTE_ID"
Write-Host ""

# ── 1. Gravar SQL em arquivo (array evita problemas com here-string) ──────────
$sqlLinhas = @(
    "SELECT",
    "  CAST(p.CDPRO AS VARCHAR(10))               || '|' ||",
    "  TRIM(REPLACE(p.DESCR, '@', ''))             || '|' ||",
    "  TRIM(p.DESCR)                               || '|' ||",
    "  COALESCE(TRIM(p.UNIDA), '')                 || '|' ||",
    "  COALESCE(CAST(e.ESTAT    AS VARCHAR(20)),'')|| '|' ||",
    "  COALESCE(CAST(p.PRCOM    AS VARCHAR(20)),'')|| '|' ||",
    "  COALESCE(CAST(e.PRCOMCTB AS VARCHAR(20)),'')|| '|' ||",
    "  COALESCE(CAST(p.PRVEN    AS VARCHAR(20)),'')|| '|' ||",
    "  COALESCE(TRIM(p.CATEGORIA), '')             || '|' ||",
    "  COALESCE(TRIM(p.PRINCIPIOATIVO), '')        || '|' ||",
    "  COALESCE(CAST(p.DENSIDADE AS VARCHAR(15)),'')|| '|' ||",
    "  COALESCE(CAST(p.FATOR    AS VARCHAR(15)),'')|| '|' ||",
    "  COALESCE(TRIM(p.CDDCB), '')                 || '|' ||",
    "  COALESCE(CAST(p.DOMIN    AS VARCHAR(15)),'')|| '|' ||",
    "  COALESCE(TRIM(p.UNIDMIN), '')               || '|' ||",
    "  COALESCE(CAST(p.DOMAX    AS VARCHAR(15)),'')|| '|' ||",
    "  COALESCE(TRIM(p.UNIDM), '')                 || '|' ||",
    "  COALESCE(TRIM(p.OBSCOMPO), '')",
    "FROM FC03000 p",
    "JOIN FC03100 e ON e.CDPRO = p.CDPRO AND e.CDFIL = 1",
    "WHERE p.SITUA = 'A' AND p.GRUPO = 'M' AND e.ESTAT > 0",
    "ORDER BY TRIM(REPLACE(p.DESCR, '@', ''));",
    "QUIT;"
)
$sqlLinhas | Set-Content -Path $SQL_TMP -Encoding ASCII

# ── 2. Executar isql ──────────────────────────────────────────────────────────
Write-Host "Extraindo do Firebird..." -ForegroundColor Yellow
$raw = & "$FB25" -user SYSDBA -password masterkey -input "$SQL_TMP" "$DB" 2>&1

$linhas = $raw | Where-Object {
    $l = [string]$_
    $l -match '\|' -and
    $l -notmatch '={5,}' -and
    $l -notmatch 'CONCATENATION' -and
    $l -notmatch 'Database:'
} | ForEach-Object { ([string]$_).Trim() }

if ($linhas.Count -eq 0) {
    Write-Host "ERRO: Nenhum dado retornado do Firebird." -ForegroundColor Red
    exit 1
}
Write-Host "Extraidos: $($linhas.Count) ativos" -ForegroundColor Green

# ── 3. Montar payload ─────────────────────────────────────────────────────────
$agora   = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")
$payload = [System.Collections.Generic.List[hashtable]]::new()

# Mapeamento Firebird UNIDA -> unidade_padrao (constraint aceita: g, mcg, mg, UI)
$unidade_map = @{
    'G'    = 'g';   'GR'   = 'g';   'KG'   = 'g';   'L'    = 'g'
    'MG'   = 'mg';  'MEQ'  = 'mg'
    'MCG'  = 'mcg'; 'UG'   = 'mcg'; 'NG'   = 'mcg'
    'UI'   = 'UI';  'IU'   = 'UI';  'U'    = 'UI'
    'ML'   = 'g';   'UN'   = 'mg';  'CAP'  = 'mg';  'CAPS' = 'mg'
}

foreach ($linha in $linhas) {
    $c = $linha.Split('|')
    if ($c.Count -lt 8) { continue }

    $unidade      = $c[3].Trim()
    $unid_key     = $unidade.ToUpper()
    $unid_padrao  = if ($unidade_map.ContainsKey($unid_key)) { $unidade_map[$unid_key] } else { 'mg' }

    $obj = @{
        cliente_id         = $CLIENTE_ID
        codigo_fc          = $c[0].Trim()
        nome               = $c[1].Trim()
        nome_original      = $c[2].Trim()
        nome_alt           = ""
        unidade            = $unidade
        unidade_padrao     = $unid_padrao   # mapeado para valor aceito pelo check constraint
        estoque_atual      = if ($c[4] -match '^\d') { [double]$c[4] } else { $null }
        em_estoque         = ($c[4] -match '^\d') -and ([double]$c[4] -gt 0)
        preco_compra       = if ($c[5] -match '^\d') { [double]$c[5] } else { $null }
        preco_custo        = if ($c[6] -match '^\d') { [double]$c[6] } else { $null }
        custo_por_unidade  = if ($c[6] -match '^\d') { [double]$c[6] } else { 0 }
        preco_venda        = if ($c[7] -match '^\d') { [double]$c[7] } else { 0 }
        margem_padrao      = $null
        categoria          = $c[8].Trim()
        classe_terapeutica = $null
        principio_ativo    = $c[9].Trim()
        controlado         = $false
        densidade          = if ($c[10] -match '^\d') { [double]$c[10] } else { 1 }
        fator_correcao     = if ($c[11] -match '^\d') { [double]$c[11] } else { 1 }
        fator_perda        = 1.0
        excipiente_padrao  = $null
        dcb                = $c[12].Trim()
        dose_min           = if ($c.Count -gt 13 -and $c[13] -match '^\d') { [double]$c[13] } else { $null }
        uni_dose_min       = if ($c.Count -gt 14) { $c[14].Trim() } else { $null }
        dose_minima_padrao = $null
        dose_max           = if ($c.Count -gt 15 -and $c[15] -match '^\d') { [double]$c[15] } else { $null }
        uni_dose_max       = if ($c.Count -gt 16) { $c[16].Trim() } else { $null }
        dose_maxima_padrao = $null
        observacoes        = if ($c.Count -gt 17) { $c[17].Trim() } else { "" }
        ativo              = $true
        sincronizado_em    = $agora
        atualizado_em      = $agora
    }

    $payload.Add($obj)
}

# ── 4. Sync completo: DELETE + INSERT em lotes de 200 ────────────────────────
# (merge-duplicates requer UNIQUE CONSTRAINT; o índice existente é apenas INDEX)
$BATCH    = 200
$total    = $payload.Count
$enviados = 0
$erros    = 0

$h_del = @{
    "apikey"        = $SUPABASE_KEY
    "Authorization" = "Bearer $SUPABASE_KEY"
}
$h_ins = @{
    "apikey"        = $SUPABASE_KEY
    "Authorization" = "Bearer $SUPABASE_KEY"
    "Content-Type"  = "application/json"
    "Prefer"        = "return=minimal"
}
$url = "$SUPABASE_URL/rest/v1/ativos"

# 4a. Remove todos os ativos deste cliente (todos vêm do Firebird, sem risco de apagar manual)
Write-Host "Removendo ativos FC existentes..." -ForegroundColor Yellow
$del_url = "${url}?cliente_id=eq.${CLIENTE_ID}"
try {
    Invoke-RestMethod -Uri $del_url -Method Delete -Headers $h_del -UserAgent "TAO-Suite-Sync/1.0" -ErrorAction Stop | Out-Null
    Write-Host "  Limpeza: OK" -ForegroundColor Green
} catch {
    try {
        $stream  = $_.Exception.Response.GetResponseStream()
        $detalhe = (New-Object System.IO.StreamReader($stream)).ReadToEnd()
    } catch { $detalhe = "$_" }
    Write-Host "  ERRO limpeza: $detalhe" -ForegroundColor Red
    exit 1
}

# 4b. Insere em lotes
Write-Host "Inserindo $total ativos no Supabase em lotes de $BATCH..." -ForegroundColor Yellow

for ($i = 0; $i -lt $total; $i += $BATCH) {
    $fim  = [Math]::Min($i + $BATCH - 1, $total - 1)
    $lote = $payload[$i..$fim]
    $json = $lote | ConvertTo-Json -Depth 5 -Compress

    try {
        Invoke-RestMethod -Uri $url -Method Post -Headers $h_ins -Body $json -UserAgent "TAO-Suite-Sync/1.0" -ErrorAction Stop | Out-Null
        $enviados += $lote.Count
        Write-Host "  Lote $([Math]::Floor($i/$BATCH)+1): $($lote.Count) registros OK" -ForegroundColor Green
    } catch {
        $erros++
        try {
            $stream = $_.Exception.Response.GetResponseStream()
            $detalhe = (New-Object System.IO.StreamReader($stream)).ReadToEnd()
        } catch { $detalhe = "$_" }
        Write-Host "  Lote $([Math]::Floor($i/$BATCH)+1): ERRO -- $detalhe" -ForegroundColor Red
    }
}

# ── 5. Resumo ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "Sincronizacao concluida:" -ForegroundColor Cyan
Write-Host "  Extraidos do Firebird : $total"
Write-Host "  Enviados ao Supabase  : $enviados"
if ($erros -gt 0) { Write-Host "  Erros de lote        : $erros" -ForegroundColor Red }
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""
