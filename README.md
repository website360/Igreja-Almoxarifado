# 🏛️ Igreja Conectada - Sistema de Gestão

Sistema web completo de gestão para igrejas, focado em **Eventos**, **Presenças**, **Comunicação** e módulos administrativos.

## ✨ Funcionalidades

### Módulos Principais
- **Dashboard** - Visão geral com KPIs, gráficos e alertas
- **Eventos/Agenda** - CRUD completo com configuração de check-in
- **Presenças/Check-in** - Manual, QR Code e via Secretaria
- **Justificativas** - Fluxo de aprovação de ausências
- **Pessoas (Secretaria)** - Cadastro completo de membros
- **Almoxarifado** - Controle de itens e empréstimos
- **Relatórios** - Por evento, pessoa, ministério e mensal
- **Integrações** - WhatsApp (Z-API) e Email SMTP
- **Configurações** - Parâmetros gerais do sistema
- **Usuários & Permissões** - RBAC completo

### Recursos
- ✅ Layout moderno e 100% responsivo
- ✅ Sistema RBAC com papéis e permissões granulares
- ✅ Auditoria completa de ações
- ✅ Automação WhatsApp para ausentes
- ✅ Exportação CSV/Excel
- ✅ Gráficos e estatísticas
- ✅ Suporte a LGPD (consentimento de comunicação)

## 🚀 Instalação

### Requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior (ou MariaDB 10.3+)
- Apache com mod_rewrite
- XAMPP, WAMP, LAMP ou similar

### Passo a Passo

1. **Clone/Copie os arquivos para o diretório do servidor web:**
```bash
# Se estiver usando XAMPP
/Applications/XAMPP/xamppfiles/htdocs/SISTEMA IGREJA 2026/
```

2. **Crie o banco de dados:**
```bash
# Acesse o phpMyAdmin ou terminal MySQL
mysql -u root -p

# Execute o schema
source /caminho/para/database/schema.sql
source /caminho/para/database/seeds.sql
```

Ou via phpMyAdmin:
- Acesse http://localhost/phpmyadmin
- Crie um banco chamado `igreja_sistema`
- Importe os arquivos `database/schema.sql` e `database/seeds.sql`

3. **Configure o banco de dados:**
Edite o arquivo `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'igreja_sistema');
define('DB_USER', 'root');
define('DB_PASS', ''); // Sua senha do MySQL
```

4. **Configure a URL da aplicação:**
Edite o arquivo `config/app.php`:
```php
define('APP_URL', 'http://localhost/SISTEMA%20IGREJA%202026');
```

5. **Crie a pasta de uploads:**
```bash
mkdir -p uploads/eventos uploads/pessoas uploads/justificativas uploads/almoxarifado uploads/config
chmod 755 uploads
```

6. **Acesse o sistema:**
```
http://localhost/SISTEMA%20IGREJA%202026/
```

### Credenciais Padrão
- **Email:** admin@igreja.com
- **Senha:** admin123

⚠️ **Importante:** Altere a senha após o primeiro login!

## 📁 Estrutura do Projeto

```
SISTEMA IGREJA 2026/
├── assets/
│   ├── css/
│   │   └── app.css          # Estilos principais
│   └── js/
│       └── app.js           # JavaScript principal
├── config/
│   ├── app.php              # Configurações gerais
│   └── database.php         # Conexão com banco
├── database/
│   ├── schema.sql           # Estrutura do banco
│   └── seeds.sql            # Dados iniciais
├── includes/
│   ├── init.php             # Inicialização
│   ├── helpers.php          # Funções auxiliares
│   ├── auth.php             # Autenticação
│   ├── permissions.php      # Sistema RBAC
│   ├── audit.php            # Auditoria
│   ├── header.php           # Header do layout
│   └── footer.php           # Footer do layout
├── dashboard/
│   └── index.php            # Dashboard principal
├── eventos/
│   ├── index.php            # Lista de eventos
│   ├── criar.php            # Criar/Editar evento
│   ├── ver.php              # Detalhes do evento
│   └── api.php              # API de eventos
├── presencas/
│   ├── index.php            # Lista de presenças
│   ├── checkin.php          # Página de check-in
│   └── api.php              # API de presenças
├── justificativas/
│   ├── index.php            # Lista de justificativas
│   ├── criar.php            # Nova justificativa
│   ├── avaliar.php          # Avaliar justificativa
│   └── api.php              # API de justificativas
├── pessoas/
│   ├── index.php            # Lista de pessoas
│   ├── criar.php            # Criar/Editar pessoa
│   └── api.php              # API de pessoas
├── almoxarifado/
│   ├── index.php            # Lista de itens
│   ├── criar.php            # Criar/Editar item
│   └── api.php              # API do almoxarifado
├── relatorios/
│   └── index.php            # Relatórios
├── integracoes/
│   ├── index.php            # Configurações de integração
│   └── api.php              # API de integrações
├── configuracoes/
│   └── index.php            # Configurações gerais
├── usuarios/
│   ├── index.php            # Usuários e permissões
│   └── api.php              # API de usuários
├── uploads/                  # Arquivos enviados
├── index.php                 # Página inicial
├── login.php                 # Login
├── logout.php                # Logout
├── perfil.php                # Perfil do usuário
├── recuperar-senha.php       # Recuperação de senha
├── acesso-negado.php         # Página de acesso negado
├── .htaccess                 # Configurações Apache
└── README.md                 # Este arquivo
```

## 👥 Papéis Padrão

| Papel | Descrição |
|-------|-----------|
| **Admin** | Acesso total ao sistema |
| **Líder** | Gerencia eventos, aprova justificativas, relatórios |
| **Secretaria** | Cadastra pessoas, check-in, relatórios |
| **Membro** | Visualiza eventos, check-in próprio, justificativas |

## 🔐 Segurança

- Senhas com hash bcrypt
- Proteção CSRF em todos os formulários
- Sessões seguras com token de "lembrar-me"
- Auditoria de ações críticas
- Proteção de diretórios sensíveis via .htaccess
- Sanitização de inputs

## 📱 Integração WhatsApp (Z-API)

1. Crie uma conta em [z-api.io](https://z-api.io)
2. Configure sua instância e obtenha:
   - Instance ID
   - Token
   - Client Token (API Key)
3. No sistema, vá em **Integrações** e configure os dados
4. Teste enviando uma mensagem de teste

## 🛠️ Desenvolvimento

### Tecnologias Utilizadas
- **Backend:** PHP 7.4+
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **CSS:** Custom CSS com variáveis (sem framework)
- **Ícones:** Lucide Icons
- **Gráficos:** Chart.js
- **Fontes:** Google Fonts (Inter)

### Padrões
- PSR-4 para autoload (simplificado)
- PDO para acesso ao banco
- Prepared statements para SQL
- MVC simplificado (sem framework)

## 📄 Licença

Este projeto foi desenvolvido para uso interno de igrejas e comunidades religiosas.

---

Desenvolvido com ❤️ e ☕
