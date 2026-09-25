# Registro de Presença em Reuniões

Sistema web **em PHP puro** (sem framework, sem Composer) para registro de presença
em reuniões. A aplicação é deliberadamente limitada: só o fluxo essencial.

## Funcionalidades

- Cadastro com e-mail + senha; envio de **e-mail de confirmação** com link de ativação.
- Login somente após confirmar a conta.
- Usuário logado pode **criar reuniões** definindo o **período** (início e fim) em que
  o registro de presença é permitido.
- O registro de presença guarda **dia/hora do registro** e o **IP** de acesso.
- Lista de presenças por reunião (visível ao criador).
- Banco **SQLite** embutido (zero configuração). E-mails enviados via SMTP simples
  (MailHog em desenvolvimento — sem senhas de e-mail reais no código).

## Como rodar (Docker)

```bash
docker compose up --build
```

- Aplicação: http://localhost:8080
- MailHog (caixa de entrada fake p/ ver o e-mail de confirmação): http://localhost:8025

Fluxo de teste: cadastre-se → abra o MailHog → clique no link do e-mail → faça login
→ crie uma reunião com período cobrindo o horário atual → registre a presença.

## Configuração (variáveis de ambiente)

| Variável    | Padrão                   | Descrição                                   |
|-------------|--------------------------|---------------------------------------------|
| APP_URL     | (deduzido da requisição) | URL base usada nos links de confirmação     |
| TZ          | UTC                      | Fuso das datas registradas                  |
| SMTP_HOST   | mailhog                  | Host SMTP para os e-mails                   |
| SMTP_PORT   | 1025                     | Porta SMTP                                  |
| MAIL_FROM   | no-reply@presenca.local  | Remetente dos e-mails                       |

Em produção, aponte `SMTP_HOST/SMTP_PORT` para um servidor SMTP real (ou use um
serviço que aceite relay sem autenticação interna) e defina `APP_URL`.

## Estrutura

```
app/
  public/      # páginas PHP (document root)
  src/         # config, helpers, mailer SMTP mínimo
  templates/   # views HTML simples
  var/         # banco SQLite (criado automaticamente)
Dockerfile     # php:8.3-apache + pdo_sqlite
docker-compose.yml
```

## Decisões

- **PHP foi boa ideia sim**: para essa aplicação pequena e limitada, PHP puro com
  servidor embutido no Docker é a opção mais simples e "atualiza bem" (trocou-se o
  `mail()` tradicional, que costuma quebrar em containers, por um cliente SMTP
  mínimo apontando para MailHog em dev).
- CSRF tokens em todos os formulários, senhas com `password_hash`, consultas com
  prepared statements, 1 registro de presença por usuário/reunião (constraint UNIQUE).
