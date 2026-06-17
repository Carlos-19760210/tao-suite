# ============================================================
# sincronizar_tao.ps1
# Equaliza Formula Certa -> Supabase (TAO Suite)
# Sincroniza: MPs (GRUPO=M) + Embalagens (GRUPO=E) + Tipos de Capsula
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

# ── Headers Supabase ──────────────────────────────────────────────────────────
$h_del = @{ "apikey" = $SUPABASE_KEY; "Authorization" = "Bearer $SUPABASE_KEY" }
$h_ins = @{
    "apikey"        = $SUPABASE_KEY
    "Authorization" = "Bearer $SUPABASE_KEY"
    "Content-Type"  = "application/json"
    "Prefer"        = "return=minimal"
}

# ── Mapeamento Firebird UNIDA -> unidade_padrao ───────────────────────────────
$unidade_map_mp = @{
    'G'    = 'g';   'GR'   = 'g';   'KG'   = 'g';   'L'    = 'g'
    'MG'   = 'mg';  'MEQ'  = 'mg'
    'MCG'  = 'mcg'; 'UG'   = 'mcg'; 'NG'   = 'mcg'
    'UI'   = 'UI';  'IU'   = 'UI';  'U'    = 'UI'
    'UFC'  = 'UFC'
    'ML'   = 'g';   'UN'   = 'mg';  'CAP'  = 'mg';  'CAPS' = 'mg'
}
$unidade_map_emb = @{
    'UN'   = 'un';  'CAP'  = 'un';  'CAPS' = 'un';  'PC'   = 'un'
    'PAR'  = 'un';  'KIT'  = 'un';  'RL'   = 'un';  'ENV'  = 'un'
    'G'    = 'g';   'MG'   = 'mg';  'ML'   = 'g';   'L'    = 'g'
}

# ── Helper: monta objeto ativo para o Supabase ───────────────────────────────
# Colunas do SELECT (0-based):
# 0=CDPRO 1=NOME_LIMPO 2=NOME_ORIG 3=UNIDA 4=ESTAT 5=PRCOM 6=PRCOMCTB
# 7=PRVEN 8=CATEGORIA 9=PRINCIPIOATIVO 10=DENSIDADE 11=FATOR
# 12=CDDCB 13=DOMIN 14=UNIDMIN 15=DOMAX 16=UNIDM 17=OBSCOMPO
# 18=DILUICAO 19=TEOR 20=QTBLH 21=QTMLH 22=QTUFC 23=QTUI  (do lote mais recente com conc.)
function Build-Ativo($c, $grupo, $agora, $unidade_map) {
    $unidade     = $c[3].Trim()
    $unid_key    = $unidade.ToUpper()
    $unid_padrao = if ($unidade_map.ContainsKey($unid_key)) { $unidade_map[$unid_key] } else {
                       if ($grupo -eq 'E') { 'un' } else { 'mg' }
                   }

    # diluicao e teor (colunas 18 e 19)
    $diluicao_raw = if ($c.Count -gt 18) { $c[18].Trim() } else { '1' }
    $teor_raw     = if ($c.Count -gt 19) { $c[19].Trim() } else { '100' }
    $diluicao_val = if ($diluicao_raw -match '^\d') { [double]$diluicao_raw } else { 1.0 }
    $teor_val     = if ($teor_raw     -match '^\d') { [double]$teor_raw     } else { 100.0 }
    if ($diluicao_val -le 0) { $diluicao_val = 1.0 }
    if ($teor_val     -le 0) { $teor_val     = 100.0 }

    # concentracao UFC/UI por grama — lote mais recente com dados (FC03140)
    # 20=QTBLH(bilhoes) 21=QTMLH(milhoes) 22=QTUFC(absoluto) 23=QTUI(absoluto)
    $qtblh = if ($c.Count -gt 20 -and $c[20].Trim() -match '^\d') { [double]$c[20].Trim() } else { 0.0 }
    $qtmlh = if ($c.Count -gt 21 -and $c[21].Trim() -match '^\d') { [double]$c[21].Trim() } else { 0.0 }
    $qtufc = if ($c.Count -gt 22 -and $c[22].Trim() -match '^\d') { [double]$c[22].Trim() } else { 0.0 }
    $qtui  = if ($c.Count -gt 23 -and $c[23].Trim() -match '^\d') { [double]$c[23].Trim() } else { 0.0 }
    $concentracao_val = $null
    if     ($qtblh -gt 0) { $concentracao_val = $qtblh * 1000000000.0 }  # BLH x 10^9 = UFC/g
    elseif ($qtmlh -gt 0) { $concentracao_val = $qtmlh * 1000000.0    }  # MLH x 10^6 = UFC ou UI/g
    elseif ($qtufc -gt 0) { $concentracao_val = $qtufc                 }  # UFC ja absoluto
    elseif ($qtui  -gt 0) { $concentracao_val = $qtui                  }  # UI ja absoluto

    return @{
        cliente_id         = $script:CLIENTE_ID
        codigo_fc          = $c[0].Trim()
        nome               = $c[1].Trim()
        nome_original      = $c[2].Trim()
        nome_alt           = ""
        grupo              = $grupo
        unidade            = $unidade
        unidade_padrao     = $unid_padrao
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
        densidade          = if ($c[10] -match '^\d') { [double]$c[10] } else { 1.0 }
        fator_correcao     = if ($c[11] -match '^\d') { [double]$c[11] } else { 1.0 }
        fator_perda        = 1.0
        diluicao           = $diluicao_val
        teor               = $teor_val
        excipiente_padrao  = $null
        dcb                = $c[12].Trim()
        dose_min           = if ($c.Count -gt 13 -and $c[13] -match '^\d') { [double]$c[13] } else { $null }
        uni_dose_min       = if ($c.Count -gt 14) { $c[14].Trim() } else { $null }
        dose_minima_padrao = $null
        dose_max           = if ($c.Count -gt 15 -and $c[15] -match '^\d') { [double]$c[15] } else { $null }
        uni_dose_max       = if ($c.Count -gt 16) { $c[16].Trim() } else { $null }
        dose_maxima_padrao = $null
        observacoes        = if ($c.Count -gt 17) { $c[17].Trim() } else { "" }
        concentracao       = $concentracao_val
        ativo              = $true
        sincronizado_em    = $agora
        atualizado_em      = $agora
    }
}

# ── Helper: extrai linhas do Firebird para um GRUPO ──────────────────────────
function Get-FCLinhas($grupo) {
    $filtroEstoque = if ($grupo -eq 'M') { "JOIN FC03100 e ON e.CDPRO = p.CDPRO AND e.CDFIL = 1" } `
                     else                { "LEFT JOIN FC03100 e ON e.CDPRO = p.CDPRO AND e.CDFIL = 1" }
    $whereEstoque  = if ($grupo -eq 'M') { "AND e.ESTAT > 0" } else { "" }

    $sqlLinhas = @(
        "SELECT",
        "  CAST(p.CDPRO AS VARCHAR(10))                    || '|' ||",
        "  TRIM(REPLACE(p.DESCR, '@', ''))                  || '|' ||",
        "  TRIM(p.DESCR)                                    || '|' ||",
        "  COALESCE(TRIM(p.UNIDA), '')                      || '|' ||",
        "  COALESCE(CAST(e.ESTAT     AS VARCHAR(20)),'')    || '|' ||",
        "  COALESCE(CAST(p.PRCOM     AS VARCHAR(20)),'')    || '|' ||",
        "  COALESCE(CAST(e.PRCOMCTB  AS VARCHAR(20)),'')    || '|' ||",
        "  COALESCE(CAST(p.PRVEN     AS VARCHAR(20)),'')    || '|' ||",
        "  COALESCE(TRIM(p.CATEGORIA), '')                  || '|' ||",
        "  COALESCE(TRIM(p.PRINCIPIOATIVO), '')             || '|' ||",
        "  COALESCE(CAST(p.DENSIDADE AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(CAST(p.FATOR     AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(TRIM(p.CDDCB), '')                      || '|' ||",
        "  COALESCE(CAST(p.DOMIN     AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(TRIM(p.UNIDMIN), '')                    || '|' ||",
        "  COALESCE(CAST(p.DOMAX     AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(TRIM(p.UNIDM), '')                      || '|' ||",
        "  COALESCE(TRIM(p.OBSCOMPO), '')                   || '|' ||",
        "  COALESCE(CAST(p.DILUICAO  AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(CAST(p.TEOR      AS VARCHAR(15)),'')    || '|' ||",
        "  COALESCE(CAST(lot.QTBLH   AS VARCHAR(30)),'')    || '|' ||",
        "  COALESCE(CAST(lot.QTMLH   AS VARCHAR(30)),'')    || '|' ||",
        "  COALESCE(CAST(lot.QTUFC   AS VARCHAR(30)),'')    || '|' ||",
        "  COALESCE(CAST(lot.QTUI    AS VARCHAR(30)),'')",
        "FROM FC03000 p",
        $filtroEstoque,
        "LEFT JOIN FC03140 lot ON lot.CDFIL=1 AND lot.CDPRO=p.CDPRO",
        "  AND lot.CTLOT = (",
        "    SELECT MAX(l2.CTLOT) FROM FC03140 l2",
        "    WHERE l2.CDFIL=1 AND l2.CDPRO=p.CDPRO",
        "      AND (l2.QTBLH > 0 OR l2.QTMLH > 0 OR l2.QTUFC > 0 OR l2.QTUI > 0)",
        "  )",
        "WHERE p.SITUA = 'A' AND p.GRUPO = '$grupo' $whereEstoque",
        "ORDER BY TRIM(REPLACE(p.DESCR, '@', ''));",
        "QUIT;"
    )
    $sqlLinhas | Set-Content -Path $script:SQL_TMP -Encoding ASCII
    $raw = & $script:FB25 -user SYSDBA -password masterkey -input $script:SQL_TMP $script:DB 2>&1
    return $raw | Where-Object {
        $l = [string]$_
        $l -match '\|' -and $l -notmatch '={5,}' -and $l -notmatch 'CONCATENATION' -and $l -notmatch 'Database:'
    } | ForEach-Object { ([string]$_).Trim() }
}

# ── Helper: extrai e sincroniza tipos de capsula (FC0H000 + FC0H100) ─────────
function Sync-Capsulas($agora) {
    Write-Host "Extraindo Tipos de Capsula..." -ForegroundColor Yellow

    $sqlCap = @(
        "SELECT",
        "  TRIM(h.DESCRICAO)                             || '|' ||",
        "  TRIM(CAST(c.NUMERO AS VARCHAR(5)))            || '|' ||",
        "  COALESCE(CAST(c.VOLINTERNO  AS VARCHAR(15)),'')|| '|' ||",
        "  COALESCE(CAST(c.PESOVAZIO   AS VARCHAR(15)),'')|| '|' ||",
        "  COALESCE(CAST(c.CDPRO       AS VARCHAR(10)),'')",
        "FROM FC0H100 c",
        "JOIN FC0H000 h ON h.IDTIPOCAP = c.IDTIPOCAP",
        "WHERE c.INDSTATUS = 'A'",
        "ORDER BY h.DESCRICAO, c.NUMERO;",
        "QUIT;"
    )
    $sqlCap | Set-Content -Path $script:SQL_TMP -Encoding ASCII
    $raw = & $script:FB25 -user SYSDBA -password masterkey -input $script:SQL_TMP $script:DB 2>&1
    $linhas = $raw | Where-Object {
        $l = [string]$_
        $l -match '\|' -and $l -notmatch '={5,}' -and $l -notmatch 'Database:'
    } | ForEach-Object { ([string]$_).Trim() }

    Write-Host "  Capsulas: $($linhas.Count)" -ForegroundColor Green

    if ($linhas.Count -eq 0) { return }

    # Usa hashtable para deduplicar por tipo+numero (FC pode ter entradas repetidas)
    $seen    = @{}
    $payload = [System.Collections.Generic.List[hashtable]]::new()
    foreach ($linha in $linhas) {
        $c = $linha.Split('|')
        if ($c.Count -lt 3) { continue }
        $volul = if ($c[2] -match '^\d') { [double]$c[2] } else { $null }
        if ($null -eq $volul) { continue }
        $key = "$($c[0].Trim())|$($c[1].Trim())"
        if ($seen.ContainsKey($key)) { continue }
        $seen[$key] = $true
        $payload.Add(@{
            cliente_id      = $script:CLIENTE_ID
            tipo            = $c[0].Trim()
            numero          = $c[1].Trim()
            vol_ul          = $volul
            peso_vazio_mg   = if ($c.Count -gt 3 -and $c[3] -match '^\d') { [double]$c[3] } else { $null }
            cdpro_fc        = if ($c.Count -gt 4) { $c[4].Trim() } else { $null }
            ativo           = $true
            sincronizado_em = $agora
        })
    }
    Write-Host "  Capsulas unicas: $($payload.Count)" -ForegroundColor Green

    $url_cap  = "$script:SUPABASE_URL/rest/v1/tipos_capsula"
    $h_upsert = @{
        "apikey"        = $script:SUPABASE_KEY
        "Authorization" = "Bearer $script:SUPABASE_KEY"
        "Content-Type"  = "application/json"
        "Prefer"        = "resolution=merge-duplicates,return=minimal"
    }

    $json = $payload | ConvertTo-Json -Depth 4 -Compress
    try {
        Invoke-RestMethod -Uri "${url_cap}?on_conflict=cliente_id,tipo,numero" -Method Post -Headers $h_upsert `
            -Body $json -UserAgent "TAO-Suite-Sync/1.0" -ErrorAction Stop | Out-Null
        Write-Host "  Capsulas sincronizadas: $($payload.Count)" -ForegroundColor Green
    } catch {
        try { $s = $_.Exception.Response.GetResponseStream(); $d = (New-Object System.IO.StreamReader($s)).ReadToEnd() } catch { $d = "$_" }
        Write-Host "  ERRO capsulas: $d" -ForegroundColor Red
    }
}

# ══════════════════════════════════════════════════════════════════════════════
# EXECUCAO
# ══════════════════════════════════════════════════════════════════════════════

$agora = (Get-Date).ToUniversalTime().ToString("yyyy-MM-ddTHH:mm:ssZ")

# ── 1. Extrai MPs ─────────────────────────────────────────────────────────────
Write-Host "Extraindo Materias-Primas (GRUPO=M)..." -ForegroundColor Yellow
$linhas_mp = Get-FCLinhas 'M'
Write-Host "  MPs: $($linhas_mp.Count)" -ForegroundColor Green

# ── 2. Extrai Embalagens ──────────────────────────────────────────────────────
Write-Host "Extraindo Embalagens (GRUPO=E)..." -ForegroundColor Yellow
$linhas_emb = Get-FCLinhas 'E'
Write-Host "  Embalagens: $($linhas_emb.Count)" -ForegroundColor Green

# ── 3. Monta payloads ────────────────────────────────────────────────────────
$payload = [System.Collections.Generic.List[hashtable]]::new()
foreach ($linha in $linhas_mp) {
    $c = $linha.Split('|')
    if ($c.Count -lt 8) { continue }
    $payload.Add((Build-Ativo $c 'M' $agora $unidade_map_mp))
}
foreach ($linha in $linhas_emb) {
    $c = $linha.Split('|')
    if ($c.Count -lt 8) { continue }
    $payload.Add((Build-Ativo $c 'E' $agora $unidade_map_emb))
}

Write-Host "Total para sync: $($payload.Count) registros" -ForegroundColor Cyan

# ── 4. Sync ativos: DELETE + INSERT ──────────────────────────────────────────
$BATCH    = 200
$total    = $payload.Count
$enviados = 0
$erros    = 0
$url      = "$SUPABASE_URL/rest/v1/ativos"

Write-Host "Removendo registros existentes do cliente..." -ForegroundColor Yellow
try {
    Invoke-RestMethod -Uri "${url}?cliente_id=eq.${CLIENTE_ID}" `
        -Method Delete -Headers $h_del -UserAgent "TAO-Suite-Sync/1.0" -ErrorAction Stop | Out-Null
    Write-Host "  Limpeza: OK" -ForegroundColor Green
} catch {
    try { $stream = $_.Exception.Response.GetResponseStream(); $detalhe = (New-Object System.IO.StreamReader($stream)).ReadToEnd() } catch { $detalhe = "$_" }
    Write-Host "  ERRO limpeza: $detalhe" -ForegroundColor Red; exit 1
}

Write-Host "Inserindo $total registros em lotes de $BATCH..." -ForegroundColor Yellow
for ($i = 0; $i -lt $total; $i += $BATCH) {
    $fim  = [Math]::Min($i + $BATCH - 1, $total - 1)
    $lote = $payload[$i..$fim]
    $json = $lote | ConvertTo-Json -Depth 5 -Compress
    try {
        Invoke-RestMethod -Uri $url -Method Post -Headers $h_ins -Body $json -UserAgent "TAO-Suite-Sync/1.0" -ErrorAction Stop | Out-Null
        $enviados += $lote.Count
        Write-Host "  Lote $([Math]::Floor($i/$BATCH)+1): $($lote.Count) OK" -ForegroundColor Green
    } catch {
        $erros++
        try { $stream = $_.Exception.Response.GetResponseStream(); $detalhe = (New-Object System.IO.StreamReader($stream)).ReadToEnd() } catch { $detalhe = "$_" }
        Write-Host "  Lote $([Math]::Floor($i/$BATCH)+1): ERRO -- $detalhe" -ForegroundColor Red
    }
}

# ── 5. Sync capsulas ──────────────────────────────────────────────────────────
Sync-Capsulas $agora

# ── 6. Resumo ─────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "Sincronizacao concluida:" -ForegroundColor Cyan
Write-Host "  MPs         : $($linhas_mp.Count)"
Write-Host "  Embalagens  : $($linhas_emb.Count)"
Write-Host "  Total ativos: $total"
Write-Host "  Enviados    : $enviados"
if ($erros -gt 0) { Write-Host "  Erros       : $erros" -ForegroundColor Red }
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""
