# php2shell — Verificação das CVE-2026-109618 e CVE-2026-109619

Material de referência e um script de **diagnóstico somente-leitura** para verificar se uma
instalação de PHP está exposta às vulnerabilidades críticas conhecidas como **php2shell**,
divulgadas e corrigidas em **21/10/2026**.

> ⚠️ Este repositório **não contém exploit**. O script apenas lê a versão e a configuração
> do PHP no seu próprio servidor. Use somente em sistemas que você administra.

---

## As vulnerabilidades

| | CVE-2026-109618 | CVE-2026-109619 |
|---|---|---|
| Apelido | php2shell | php2shell |
| Tipo | Corrupção de memória em falta de memória (OOM) | Uso de memória já liberada (estado global antigo) |
| CVSS v4.0 | 9.2 (crítica) | 9.2 (crítica) |
| Impacto | Execução remota de código sem autenticação | Execução remota de código sem autenticação + vazamento de memória |
| Advisory | [GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) | [GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp) |

**CVE-2026-109618:** quando uma requisição ultrapassa o `memory_limit`, o PHP aborta no meio
da operação. Em alguns pontos isso ocorre com dados internos montados pela metade, e a limpeza
da requisição passa a tratar dados inválidos como válidos.

**CVE-2026-109619:** dados internos de uma requisição continuam existindo depois que sua memória
foi liberada. A próxima requisição atendida pelo mesmo worker pode usar esse estado antigo antes
de ele ser reinicializado. O recurso de progresso de upload pode devolver ao cliente memória
liberada do worker dentro do cookie `PHPSESSID`.

**Mais expostos:** instalações com **mod_php** (o ataque ocorre antes do código da aplicação
rodar). **PHP-FPM** também é afetado. Um proxy/CDN na frente (ex.: Cloudflare) **não protege**:
as requisições chegam ao PHP como requisições normais.

---

## Versões corrigidas

| Branch | Versão corrigida |
|---|---|
| 8.5 | **8.5.12** |
| 8.4 | **8.4.27** |
| 8.3 | **8.3.36** |
| 8.2 | **8.2.35** |
| 8.6 | 8.6.0 RC4 já inclui a correção |

- **PHP 8.1 e anteriores** não recebem mais versões oficiais. O time de segurança publicou
  apenas patches mínimos de código-fonte para 7.4, 8.0 e 8.1.
- **PHP 5.x / 7.0–7.3:** sem correção oficial — é necessário migrar para 8.2+.
- **PHP 8.2** chega ao fim da vida em 31/12/2026.
- Distribuições Linux e fornecedores LTS receberam patches antecipados — verifique os pacotes
  da sua distro/hospedagem.

### Como interpretar a versão instalada

| Versão instalada | Situação |
|---|---|
| ≥ 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 | Corrigido ✅ |
| 8.2–8.5 abaixo disso | **Vulnerável** → atualize |
| 8.1 ou anterior | **Vulnerável e sem correção oficial** → migre p/ 8.2+ |

---

## Mitigações temporárias (parciais — atualizar é a única correção completa)

**CVE-2026-109619:**
- `session.upload_progress.enabled = 0` no `php.ini` / pool do FPM / config do servidor
  (aplicado **antes** do parsing da requisição), e reinicie os workers.
  `ini_set()` ou `.user.ini` **não** servem para isso.
- Apenas no PHP 8.5: `fatal_error_backtraces = 0` e reinicie os workers.

**CVE-2026-109618:**
- Limite o tamanho das requisições (`post_max_size`, `upload_max_filesize`) e aplique limites
  também no servidor web / proxy (tamanho do corpo decodificado, nº de campos, partes multipart,
  cabeçalhos e profundidade de aninhamento).
- Pode usar `memory_limit = -1` junto com um limite de memória por **cgroup/container** que mate
  apenas o worker afetado. **Não** use `RLIMIT_AS` nem `RLIMIT_DATA`.

---

## Script de diagnóstico: `check-php2shell.php`

Diagnóstico **somente-leitura** para hospedagem sem SSH (sobe pelo painel/FTP).

### Uso

1. Edite o arquivo e troque o valor de `$TOKEN` por uma senha sua (letras e números).
2. Suba `check-php2shell.php` na raiz do site.
3. Acesse no navegador:
   ```
   https://SEU-DOMINIO/check-php2shell.php?token=SUA_SENHA
   ```
4. Leia o resultado.
5. **Apague o arquivo do servidor** ao terminar.

O relatório mostra:
- Versão do PHP e se está acima da corrigida;
- `session.upload_progress.enabled` (mitigação da 109619);
- `memory_limit`, `post_max_size`, `upload_max_filesize` (relevantes para a 109618);
- no PHP 8.5, também `fatal_error_backtraces`.

Se aparecer **"Acesso negado."**, o `token` da URL não bate com o `$TOKEN` do arquivo.

---

## Script de diagnóstico: `check-php2shell.sh`

Mesma verificação, para quem **tem acesso ao shell/SSH** do servidor.

```bash
chmod +x check-php2shell.sh
./check-php2shell.sh
```

Reporta a versão do PHP (CLI), se está corrigida, as configurações relevantes
(`session.upload_progress.enabled`, `memory_limit`, `post_max_size`, `upload_max_filesize`,
`fatal_error_backtraces`), versões do PHP-FPM/módulos e os pacotes PHP instalados.

> Observação: a versão do PHP no CLI pode diferir da usada pelo site (PHP-FPM/módulo do Apache).
> O script também lista as versões de FPM encontradas para você comparar.

---

## Situação exploração / divulgação (em 21/10/2026)

- watchTowr relata tentativas de exploração ativa; Huntress diz não haver IoCs confirmados;
  CISA KEV ainda não listava as falhas.
- Red Hat classifica como "Important" e diz que o RCE é difícil na prática; Sansec afirma que
  configurações padrão já são vulneráveis.

---

## Fontes

- [php.net — anúncio oficial](https://www.php.net/)
- [Tenable FAQ](https://www.tenable.com/blog/php2shell-cve-2026-109618-cve-2026-109619-frequently-asked-questions-about-critical-php-remote)
- [Unit 42 (Palo Alto)](https://unit42.paloaltonetworks.com/cve-2026-109618-cve-2026-109619/)
- [Sansec](https://sansec.io/research/php2shell)
- [watchTowr FAQ](https://watchtowr.com/intelligence/php-cve-2026-109618-faq/)
- [Google Cloud](https://cloud.google.com/blog/products/identity-security/responding-to-core-php-vulnerabilities-cve-2026-109618-cve-2026-109619)
- [Red Hat](https://access.redhat.com/security/cve/cve-2026-109618)
- [Help Net Security](https://www.helpnetsecurity.com/2026/10/21/php2shell-php-vulnerabilities-patched-cve-2026-109618-cve-2026-109619/)
- [AlmaLinux](https://almalinux.org/blog/2026-10-21-php2shell-cve-2026-109618-cve-2026-109619/)

---

*Gerado em 21/10/2026. Material informativo — verifique sempre a versão corrigida mais recente no php.net.*
