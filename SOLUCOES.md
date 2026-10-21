# Como corrigir as CVE-2026-109618 e CVE-2026-109619 (php2shell)

Resumo das soluções propostas pelas principais fontes de segurança (coletadas em 21/10/2026).

> **Regra de ouro:** o próprio projeto PHP afirma que **atualizar é a única correção completa**.
> Não existe mitigação de configuração genérica que resolva 100%. As configurações abaixo
> apenas **reduzem** o risco enquanto a atualização não é aplicada.

---

## 1. Solução definitiva — atualizar o PHP

Atualize para a versão corrigida do seu branch (lançadas em 21/10/2026):

| Branch | Versão corrigida |
|---|---|
| 8.5 | **8.5.12** |
| 8.4 | **8.4.27** |
| 8.3 | **8.3.36** |
| 8.2 | **8.2.35** |
| 8.6 | 8.6.0 RC4 (ou final) |

Depois de atualizar, **reinicie todos os workers** (PHP-FPM / Apache) — processos antigos
continuam rodando o runtime vulnerável até reiniciarem. Em containers, **reconstrua a imagem**
e substitua os containers.

> ⚠️ Fornecedores (distros, hospedagens) às vezes aplicam o patch **sem mudar o número da versão**.
> Confie no aviso do seu fornecedor, não apenas na string de versão.

### Linux (pacotes da distribuição)
Os pacotes vêm da sua distro/hospedagem e **não** são instalados automaticamente.

```bash
# Debian / Ubuntu
sudo apt update && sudo apt upgrade
sudo systemctl restart php*-fpm apache2   # reinicie os serviços

# RHEL / AlmaLinux / Rocky
sudo dnf update
sudo systemctl restart php-fpm httpd
```

A Ubuntu, por exemplo, publicou correções como `php8.3` em `8.3.6-0ubuntu0.24.04.13` (Noble 24.04)
e `php8.1` em `8.1.2-1ubuntu2.28` (Jammy 22.04).

### Versões em fim de vida (8.1 e anteriores)
Sem release oficial. O time do PHP publicou **apenas patches de código-fonte** para 7.4, 8.0 e 8.1
(sem binários). O recomendado é **migrar para um branch suportado (8.2+)**.

---

## 2. Hospedagem compartilhada (cPanel / Plesk / sem SSH)

Esse é o cenário mais comum para sites gerenciados.

1. **Mude a versão do PHP pelo painel** para a corrigida, se o seu host já disponibilizou:
   - cPanel: *MultiPHP Manager* → selecione a versão corrigida para o domínio.
   - Plesk: *Websites & Domains* → *PHP Settings*.
2. **Se a versão corrigida ainda não aparecer**, abra um chamado com o suporte da hospedagem
   perguntando quando aplicarão o patch das CVE-2026-109618 / 109619. (Não há, até o momento,
   um aviso específico de cPanel nessas fontes — depende do seu provedor.)
3. **Mitigação temporária com mod_php:** se o PHP roda como módulo do Apache, ele **ignora**
   `.user.ini`. Nesse caso, coloque a diretiva no `.htaccess` da raiz do site:
   ```apache
   php_flag session.upload_progress.enabled Off
   ```
   (Em PHP-FPM, essa linha no `.htaccess` não funciona — use o `php.ini` / pool, veja a seção 3.)
4. **WAF do painel:** se usar Imunify360 (comum em cPanel/CloudLinux), o **ruleset 8.83** já
   bloqueia o formato público do exploit e chega automaticamente com o WAF ativo. Não substitui
   a atualização, e regras por tamanho de corpo **não** ajudam (o exploit tem ~450 bytes).

---

## 3. Mitigações temporárias (parciais) por CVE

Aplique **antes** do parsing da requisição (php.ini, pool do FPM ou config do servidor) e
**reinicie os workers**. `ini_set()` e `.user.ini` **não bastam** (o PHP diz explicitamente).

### CVE-2026-109619 (use-after-free / upload progress)
```ini
session.upload_progress.enabled = 0
```
- No PHP **8.5**, acrescente também:
  ```ini
  fatal_error_backtraces = 0
  ```
- Desativar o upload-progress reduz, mas **não elimina** o risco — é apenas um dos caminhos
  de ataque confirmados.

### CVE-2026-109618 (corrupção de memória em OOM)
```ini
memory_limit = -1
```
- Defina preferencialmente via `php_admin_value` no pool do FPM ou na config do Apache, e
  garanta que nenhum pool/diretório/aplicação volte a impor um limite finito.
- Combine com um limite de memória por **cgroup/container** que mate **apenas** o worker afetado.
  **Não** use `RLIMIT_AS` nem `RLIMIT_DATA` (podem disparar a própria falha).
- Limite o tamanho das requisições no PHP e **também** no servidor web / proxy:
  ```ini
  post_max_size = 8M        ; sem uploads
  post_max_size = 32M       ; se precisar de uploads
  upload_max_filesize = <menor que post_max_size>
  ```
- Atenção: com **mod_php**, até um POST de formulário normal pode disparar a 109618.

---

## 4. Camada de rede / defesa adicional

- **Isolar aplicações expostas** à internet ou colocá-las atrás de autenticação na camada de
  rede (recomendação da Sophos como paliativo).
- **Reverse proxy / WAF** na frente do PHP pode bloquear parte das requisições, mas **não protege**
  sozinho — as requisições maliciosas chegam ao PHP como requisições normais. Um CDN como o
  Cloudflare, por si só, não resolve.

---

## 5. Verificar comprometimento

A Sophos sugere **revisar a atividade de sistemas expostos a partir de 01/08/2026**, já que as
falhas podem ter sido conhecidas por terceiros antes da divulgação. A Sansec observou que o
handler de upload-progress pode devolver memória liberada do worker dentro do cookie `PHPSESSID`
— logs com cookies de sessão malformados podem ser um indício.

Ferramentas de terceiros (community, **não verificadas aqui** — revise antes de usar em produção):
- `HOSTED-POWER/php_uploadprogress_guard` — extensão que desanexa o handler de upload-progress (cPanel EA4, Debian/Sury, DirectAdmin).
- `ersinkoc/php-fix` — script que desativa `session.upload_progress` em todas as instalações.

---

## Checklist rápido

- [ ] Atualizei o PHP para 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12 (ou 8.6 final)?
- [ ] Reiniciei PHP-FPM / Apache (ou reconstruí os containers)?
- [ ] Confirmei a versão em uso pelo **site** (não só pelo CLI) com o `check-php2shell`?
- [ ] Enquanto não atualizei: `session.upload_progress.enabled = 0` + restart dos workers?
- [ ] Em mod_php sem SSH: coloquei `php_flag session.upload_progress.enabled Off` no `.htaccess`?
- [ ] Revisei logs/atividade suspeita desde 01/08/2026?

---

## Fontes

- [php.net — anúncio oficial](https://www.php.net/)
- [Datadog Security Labs — detecção, análise e remediação](https://securitylabs.datadoghq.com/articles/php2shell-critical-vulnerabilities-in-php/)
- [Sophos — advisory](https://www.sophos.com/en-us/security-advisories/sophos-sa-20261021-php2shell)
- [Ubuntu — correções disponíveis](https://ubuntu.com/blog/php2shell-vulnerability-fixes-available)
- [JFrog Security Research](https://research.jfrog.com/post/php2shell-all-you-need-to-know-about-cve-2026-109618-and-cve-2026-109619/)
- [Imunify360 / CloudLinux — ruleset 8.83](https://blog.imunify360.com/php-cve-2026-109618-cve-2026-109619/)
- [ma.ttias.be — early mitigation steps](https://ma.ttias.be/php-cve-2026-109618-early-mitigation/)
- [Help Net Security](https://www.helpnetsecurity.com/2026/10/21/php2shell-php-vulnerabilities-patched-cve-2026-109618-cve-2026-109619/)
- [SOCRadar](https://socradar.io/blog/php2shell-cve-2026-109618-cve-2026-109619/)

*Material informativo coletado em 21/10/2026. Verifique sempre a versão corrigida mais recente no php.net e o aviso do seu fornecedor.*
