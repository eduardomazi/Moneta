<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$csrfToken = $_SESSION['csrf_token'];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token"
          content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <title>Moneta — Controle Financeiro</title>

    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="app.js" defer></script>
</head>
<body>

    <!-- ================= LOGIN / CADASTRO ================= -->
    <main id="authView">
        <section class="auth-card">
            <div class="auth-heading">
                <span class="brand-icon">M</span>
                <h2 id="authTitle">Entre na sua conta</h2>
                <p class="muted">Seu controle financeiro pessoal.</p>
            </div>

            <form id="authForm">
                <div class="field hidden" id="nameGroup">
                    <label for="authName">Nome</label>
                    <input id="authName" name="name" type="text"
                           autocomplete="name" placeholder="Seu nome">
                </div>

                <div class="field">
                    <label for="authEmail">E-mail</label>
                    <input id="authEmail" name="email" type="email"
                           autocomplete="email" placeholder="voce@email.com" required>
                </div>

                <div class="field">
                    <label for="authPassword">Senha</label>
                    <input id="authPassword" name="password" type="password"
                           autocomplete="current-password" placeholder="Sua senha" required>
                </div>

                <button id="authSubmit" class="btn btn-primary full-width" type="submit">
                    Entrar
                </button>
                <p id="authMessage" class="message" role="status"></p>
            </form>

            <p class="auth-switch">
                <span id="authSwitchText">Ainda não tem uma conta?</span>
                <button id="authSwitch" class="text-btn" type="button">Criar conta</button>
            </p>
        </section>
    </main>

    <!-- ================= PAINEL ================= -->
    <div class="app-shell hidden" id="dashboardView">

        <aside class="sidebar">
            <div class="brand">
                <span class="brand-icon">M</span>
                <span>Moneta</span>
            </div>

            <p class="nav-label">MENU</p>
            <a class="nav-link active" href="#resumo"><b>◧</b><span>Resumo</span></a>
            <a class="nav-link" href="#transacoes"><b>↔</b><span>Transações</span></a>
            <a class="nav-link" href="#conversor"><b>$</b><span>Conversor</span></a>
            <a class="nav-link" href="#metas"><b>◎</b><span>Metas</span></a>

            <div class="sidebar-bottom">
                <div class="profile-avatar">M</div>
                <div class="profile-info">
                    <strong id="profileName">Minha conta</strong>
                    <small id="profileEmail"></small>
                </div>
                <button id="logoutBtn" class="icon-btn hidden" type="button"
                        title="Sair" aria-label="Sair">Sair</button>
            </div>
        </aside>

        <main class="main-content">

            <header class="topbar">
                <div>
                    <p class="eyebrow">VISÃO GERAL</p>
                    <h1 id="greeting">Olá.</h1>
                    <p class="muted">Acompanhe suas receitas, despesas e metas.</p>
                </div>
                <div class="topbar-right">
                    <span class="status-dot"></span>
                    <span>Sessão protegida</span>
                </div>
            </header>

            <!-- Resumo -->
            <section class="summary-grid" id="resumo">
                <article class="summary-card balance-card">
                    <div class="card-top">
                        <span>Saldo atual</span>
                        <span class="metric-icon">$</span>
                    </div>
                    <h2 id="balanceValue">R$ 0,00</h2>
                    <p class="metric-note">Receitas menos despesas</p>
                </article>

                <article class="summary-card">
                    <div class="card-top">
                        <span>Receitas</span>
                        <span class="metric-icon income-icon">↗</span>
                    </div>
                    <h2 id="incomeValue">R$ 0,00</h2>
                    <p class="metric-note">Total recebido</p>
                </article>

                <article class="summary-card">
                    <div class="card-top">
                        <span>Despesas</span>
                        <span class="metric-icon expense-icon">↘</span>
                    </div>
                    <h2 id="expenseValue">R$ 0,00</h2>
                    <p class="metric-note">Total gasto</p>
                </article>
            </section>

            <!-- Gráficos -->
            <section class="charts-grid">
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Fluxo financeiro</h3>
                            <p class="muted">Receitas e despesas por mês</p>
                        </div>
                        <span class="mini-badge">6 MESES</span>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="flowChart"></canvas>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Despesas por categoria</h3>
                            <p class="muted">Para onde seu dinheiro vai</p>
                        </div>
                    </div>
                    <div class="doughnut-wrap" style="position:relative">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="category-legend" id="categoryLegend"></div>
                </article>
            </section>

            <!-- Transações -->
            <section class="content-grid" id="transacoes">
                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Nova transação</h3>
                            <p class="muted">Registre uma receita ou despesa</p>
                        </div>
                    </div>

                    <form id="transactionForm">
                        <div class="field">
                            <label for="description">Descrição</label>
                            <input id="description" name="description"
                                   maxlength="150" placeholder="Ex.: Mercado" required>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="amount">Valor (R$)</label>
                                <input id="amount" name="amount" type="number"
                                       min="0.01" step="0.01" placeholder="0,00" required>
                            </div>
                            <div class="field">
                                <label for="type">Tipo</label>
                                <select id="type" name="type">
                                    <option value="expense">Despesa</option>
                                    <option value="income">Receita</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label for="category">Categoria</label>
                                <input id="category" name="category" list="categoryOptions"
                                       maxlength="60" placeholder="Ex.: Alimentação" required>
                                <datalist id="categoryOptions">
                                    <option value="Alimentação">
                                    <option value="Moradia">
                                    <option value="Transporte">
                                    <option value="Lazer">
                                    <option value="Saúde">
                                    <option value="Educação">
                                    <option value="Salário">
                                    <option value="Outros">
                                </datalist>
                            </div>
                            <div class="field">
                                <label for="transactionDate">Data</label>
                                <input id="transactionDate" name="date" type="date" required>
                            </div>
                        </div>

                        <button class="btn btn-primary full-width" type="submit">Adicionar</button>
                        <p id="transactionMessage" class="message" role="status"></p>
                    </form>
                </article>

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Últimas transações</h3>
                            <p class="muted">As 8 mais recentes</p>
                        </div>
                    </div>
                    <div class="transaction-list" id="transactionList"></div>
                </article>
            </section>

            <!-- Conversor -->
            <section class="panel converter-panel" id="conversor">
                <div class="panel-heading">
                    <div>
                        <h3>Conversor de moedas</h3>
                        <p class="muted">Taxas de referência via API Frankfurter</p>
                    </div>
                    <span class="api-badge"><span class="status-dot"></span> API pública</span>
                </div>

                <form id="converterForm">
                    <div class="converter-grid">
                        <div class="field">
                            <label for="convertAmount">Valor</label>
                            <input id="convertAmount" type="number" min="0"
                                   step="0.01" value="1" required>
                        </div>

                        <div class="field">
                            <label for="fromCurrency">De</label>
                            <select id="fromCurrency">
                                <option value="BRL">BRL — Real</option>
                                <option value="USD">USD — Dólar</option>
                                <option value="EUR">EUR — Euro</option>
                            </select>
                        </div>

                        <button id="swapCurrencies" class="swap-btn" type="button"
                                title="Inverter moedas" aria-label="Inverter moedas">⇄</button>

                        <div class="field">
                            <label for="toCurrency">Para</label>
                            <select id="toCurrency">
                                <option value="USD">USD — Dólar</option>
                                <option value="BRL">BRL — Real</option>
                                <option value="EUR">EUR — Euro</option>
                            </select>
                        </div>

                        <button class="btn btn-primary convert-btn" type="submit">Converter</button>
                    </div>
                </form>

                <div class="conversion-result">
                    <div>
                        <p class="eyebrow" id="conversionLabel">RESULTADO</p>
                        <h2 id="conversionValue">—</h2>
                        <p class="muted" id="conversionRate"></p>
                    </div>
                    <p class="conversion-date" id="conversionDate"></p>
                </div>

                <p id="converterMessage" class="message" role="status"></p>
                <p class="fine-print">
                    As cotações são de referência e podem diferir das praticadas por bancos e casas de câmbio.
                </p>
            </section>

            <!-- Metas -->
            <section class="panel goals-panel" id="metas">
                <div class="panel-heading">
                    <div>
                        <h3>Minhas metas</h3>
                        <p class="muted">Salvas neste navegador</p>
                    </div>
                </div>

                <form id="goalForm" class="goal-form">
                    <div class="field">
                        <label for="goalName">Nome da meta</label>
                        <input id="goalName" name="name" maxlength="60"
                               placeholder="Ex.: Viagem" required>
                    </div>
                    <div class="field">
                        <label for="goalTarget">Valor da meta</label>
                        <input id="goalTarget" name="target" type="number"
                               min="0.01" step="0.01" required>
                    </div>
                    <div class="field">
                        <label for="goalSaved">Valor guardado</label>
                        <input id="goalSaved" name="saved" type="number"
                               min="0" step="0.01" value="0" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Criar meta</button>
                </form>

                <div class="goals-list" id="goalsList"></div>
            </section>

            <footer class="footer">Moneta — Controle Financeiro</footer>
        </main>
    </div>

</body>
</html>