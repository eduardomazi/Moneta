
const API = "../api/";

const $ = (selector) => document.querySelector(selector);

let currentUser = null;
let transactions = [];
let flowChart = null;
let categoryChart = null;
let registerMode = false;

const currencyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL"
});

function money(value) {
    return currencyFormatter.format(Number(value) || 0);
}

function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[char]);
}

function message(selector, text, type = "") {
    const element = $(selector);
    element.textContent = text;
    element.className = `message ${type}`;
}

function csrfToken() {
    return $('meta[name="csrf-token"]')?.content || "";
}

async function api(path, options = {}) {
    const headers = {
        "Accept": "application/json",
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(options.method && options.method !== "GET"
            ? { "X-CSRF-Token": csrfToken() }
            : {}),
        ...options.headers
    };

    const response = await fetch(API + path, {
        credentials: "same-origin",
        ...options,
        headers
    });

    const text = await response.text();
    let data = {};

       try {
        data = text ? JSON.parse(text) : {};
    } catch {
        console.error(`Resposta não-JSON de ${API + path} (HTTP ${response.status}):`, text);
        throw new Error(`O servidor retornou uma resposta inválida (HTTP ${response.status}). Veja o console (F12).`);
    }

    if (data.csrfToken) {
        $('meta[name="csrf-token"]').content = data.csrfToken;
    }

    if (!response.ok) {
        if (response.status === 401) {
            showAuth();
        }
        throw new Error(data.error || data.message || `Erro HTTP ${response.status}`);
    }

    return data;
}

function showAuth() {
    currentUser = null;
    $("#authView").classList.remove("hidden");
    $("#dashboardView").classList.add("hidden");
    $("#logoutBtn").classList.add("hidden");
}

function showDashboard(user) {
    currentUser = user;
    $("#authView").classList.add("hidden");
    $("#dashboardView").classList.remove("hidden");
    $("#logoutBtn").classList.remove("hidden");

    $("#profileName").textContent = user.name || "Minha conta";
    $("#profileEmail").textContent = user.email || "";
    $("#greeting").textContent = `Olá, ${(user.name || "bem-vindo").split(" ")[0]}.`;

    loadTransactions();
}

function setAuthMode(register) {
    registerMode = register;

    $("#authTitle").textContent = register ? "Crie sua conta" : "Entre na sua conta";
    $("#authSubmit").textContent = register ? "Criar conta" : "Entrar";
    $("#nameGroup").classList.toggle("hidden", !register);
    $("#authPassword").autocomplete = register ? "new-password" : "current-password";
    $("#authPassword").minLength = register ? 10 : 1;
    $("#authName").required = register;

    $("#authSwitchText").textContent = register
        ? "Já tem uma conta?"
        : "Ainda não tem uma conta?";

    $("#authSwitch").textContent = register ? "Entrar" : "Criar conta";
    message("#authMessage", "");
}

$("#authSwitch").addEventListener("click", () => {
    setAuthMode(!registerMode);
});

$("#authForm").addEventListener("submit", async event => {
    event.preventDefault();

    const payload = {
        email: $("#authEmail").value.trim(),
        password: $("#authPassword").value
    };

    if (registerMode) {
        payload.name = $("#authName").value.trim();

        if (payload.password.length < 10) {
            message("#authMessage", "Use uma senha com pelo menos 10 caracteres.", "error");
            return;
        }
    }

    const endpoint = registerMode ? "register.php" : "login.php";
    $("#authSubmit").disabled = true;
    message("#authMessage", "Verificando seus dados...");

    try {
        const data = await api(endpoint, {
            method: "POST",
            body: JSON.stringify(payload)
        });

        if (data.user) {
            showDashboard(data.user);
        } else {
            message("#authMessage", data.message || "Operação concluída.", "success");
        }
    } catch (error) {
        message("#authMessage", error.message, "error");
    } finally {
        $("#authSubmit").disabled = false;
    }
});

$("#logoutBtn").addEventListener("click", async () => {
    try {
        await api("logout.php", { method: "POST", body: JSON.stringify({}) });
    } catch (error) {
        console.warn("Não foi possível confirmar o logout:", error.message);
    }

    showAuth();
    setAuthMode(false);
});

function normalizeTransactions(data) {
    const list = Array.isArray(data)
        ? data
        : data.transactions || data.data || [];

    return list.map(item => ({
        ...item,
        amount: Number(item.amount),
        type: item.type,
        category: item.category || "Outros",
        transaction_date: item.transaction_date || item.date || ""
    }));
}

async function loadTransactions() {
    try {
        const data = await api("transactions.php");
        transactions = normalizeTransactions(data);
        renderDashboard();
    } catch (error) {
        console.error(error);
        if (currentUser) {
            message("#transactionMessage",
                "Não foi possível carregar as transações: " + error.message, "error");
        }
    }
}

function renderDashboard() {
    const income = transactions
        .filter(item => item.type === "income")
        .reduce((sum, item) => sum + item.amount, 0);

    const expenses = transactions
        .filter(item => item.type === "expense")
        .reduce((sum, item) => sum + item.amount, 0);

    $("#balanceValue").textContent = money(income - expenses);
    $("#incomeValue").textContent = money(income);
    $("#expenseValue").textContent = money(expenses);

    renderCharts();
    renderTransactionList();
}

function renderCharts() {
    const months = [];
    const now = new Date();

    for (let offset = 5; offset >= 0; offset--) {
        const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
        months.push({
            year: date.getFullYear(),
            month: date.getMonth(),
            label: date.toLocaleDateString("pt-BR", { month: "short" })
        });
    }

    const incomeByMonth = months.map(month =>
        transactions
            .filter(item => {
                const date = new Date(`${item.transaction_date}T12:00:00`);
                return item.type === "income" &&
                    !Number.isNaN(date.getTime()) &&
                    date.getFullYear() === month.year &&
                    date.getMonth() === month.month;
            })
            .reduce((sum, item) => sum + item.amount, 0)
    );

    const expensesByMonth = months.map(month =>
        transactions
            .filter(item => {
                const date = new Date(`${item.transaction_date}T12:00:00`);
                return item.type === "expense" &&
                    !Number.isNaN(date.getTime()) &&
                    date.getFullYear() === month.year &&
                    date.getMonth() === month.month;
            })
            .reduce((sum, item) => sum + item.amount, 0)
    );

    if (flowChart) flowChart.destroy();

    flowChart = new Chart($("#flowChart"), {
        type: "line",
        data: {
            labels: months.map(month => month.label),
            datasets: [
                {
                    label: "Receitas",
                    data: incomeByMonth,
                    borderColor: "#5bd6a1",
                    backgroundColor: "#5bd6a122",
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                },
                {
                    label: "Despesas",
                    data: expensesByMonth,
                    borderColor: "#d6b36a",
                    backgroundColor: "#d6b36a18",
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: "index" },
            plugins: {
                legend: {
                    labels: { color: "#b8c2d0", usePointStyle: true, boxWidth: 8 }
                },
                tooltip: {
                    callbacks: {
                        label: context => `${context.dataset.label}: ${money(context.raw)}`
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: "#ffffff08" },
                    ticks: { color: "#8491a4" }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: "#ffffff0d" },
                    ticks: {
                        color: "#8491a4",
                        callback: value => "R$ " + Number(value).toLocaleString("pt-BR")
                    }
                }
            }
        }
    });

    const totals = {};

    transactions
        .filter(item => item.type === "expense")
        .forEach(item => {
            totals[item.category] = (totals[item.category] || 0) + item.amount;
        });

    const categories = Object.entries(totals).sort((a, b) => b[1] - a[1]);
    const colors = ["#d6b36a", "#5bd6a1", "#8a91f5", "#e88f9c",
                    "#68b6dc", "#bd8ce3", "#d1a77d", "#8b9aaa"];

    if (categoryChart) categoryChart.destroy();

    categoryChart = new Chart($("#categoryChart"), {
        type: "doughnut",
        data: {
            labels: categories.map(item => item[0]),
            datasets: [{
                data: categories.map(item => item[1]),
                backgroundColor: categories.map((_, i) => colors[i % colors.length]),
                borderColor: "#111720",
                borderWidth: 4,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: "72%",
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => `${context.label}: ${money(context.raw)}`
                    }
                }
            }
        }
    });

    $("#categoryLegend").innerHTML = categories.length
        ? categories.slice(0, 8).map(([name, amount], index) => `
            <div class="legend-item">
                <span class="legend-dot" style="background:${colors[index % colors.length]}"></span>
                <span>${escapeHTML(name)}</span>
                <span class="legend-value">${money(amount)}</span>
            </div>
        `).join("")
        : '<p class="muted">Adicione despesas para visualizar as categorias.</p>';
}

function renderTransactionList() {
    const list = $("#transactionList");

    const sorted = [...transactions].sort((a, b) =>
        String(b.transaction_date).localeCompare(String(a.transaction_date)) ||
        Number(b.id) - Number(a.id)
    ).slice(0, 8);

    if (!sorted.length) {
        list.innerHTML = '<p class="muted">Você ainda não tem transações. Adicione a primeira!</p>';
        return;
    }

    list.innerHTML = sorted.map(item => {
        const income = item.type === "income";
        const date = item.transaction_date
            ? new Date(`${item.transaction_date}T12:00:00`).toLocaleDateString("pt-BR")
            : "Sem data";

        return `
            <div class="transaction-item">
                <div class="transaction-icon">${income ? "↗" : "↘"}</div>
                <div class="transaction-details">
                    <strong>${escapeHTML(item.description)}</strong>
                    <small>${escapeHTML(item.category)} · ${date}</small>
                </div>
                <div class="transaction-amount ${income ? "amount-income" : "amount-expense"}">
                    ${income ? "+" : "−"}${money(item.amount)}
                </div>
                <button class="delete-btn" data-delete="${Number(item.id)}"
                        title="Excluir transação" aria-label="Excluir transação">×</button>
            </div>
        `;
    }).join("");
}

$("#transactionForm").addEventListener("submit", async event => {
    event.preventDefault();

    const payload = {
        description: $("#description").value.trim(),
        amount: Number($("#amount").value),
        type: $("#type").value,
        category: $("#category").value,
        date: $("#transactionDate").value
    };

   if (!payload.description || payload.amount <= 0 || !payload.date) {
        message("#transactionMessage", "Confira os dados da transação.", "error");
        return;
    }

    try {
        await api("transactions.php", {
            method: "POST",
            body: JSON.stringify(payload)
        });

        $("#transactionForm").reset();
        $("#transactionDate").value = new Date().toLocaleDateString("en-CA");
        message("#transactionMessage", "Transação adicionada!", "success");
        await loadTransactions();
    } catch (error) {
        message("#transactionMessage", error.message, "error");
    }
});

$("#transactionList").addEventListener("click", async event => {
    const button = event.target.closest("[data-delete]");
    if (!button) return;

    if (!confirm("Deseja excluir esta transação?")) return;

    try {
        await api(`transactions.php?id=${encodeURIComponent(button.dataset.delete)}`, {
            method: "DELETE"
        });
        await loadTransactions();
    } catch (error) {
        alert("Não foi possível excluir: " + error.message);
    }
});

// Conversão de moedas usando a API pública Frankfurter.
$("#converterForm").addEventListener("submit", convertCurrency);

async function convertCurrency(event) {
    if (event) event.preventDefault();

    const amount = Number($("#convertAmount").value);
    const from = $("#fromCurrency").value;
    const to = $("#toCurrency").value;

    if (!Number.isFinite(amount) || amount < 0) {
        message("#converterMessage", "Informe um valor válido.", "error");
        return;
    }

    if (from === to) {
        $("#conversionValue").textContent = new Intl.NumberFormat("pt-BR", {
            style: "currency", currency: to
        }).format(amount);
        $("#conversionRate").textContent = `1 ${from} = 1 ${to}`;
        $("#conversionDate").textContent = "Mesma moeda";
        $("#conversionLabel").textContent = "Resultado";
        message("#converterMessage", "");
        return;
    }

    message("#converterMessage", "Consultando taxa de câmbio...");
    $("#conversionValue").textContent = "Calculando...";

    try {
        const url = `https://api.frankfurter.dev/v1/latest?base=${encodeURIComponent(from)}&symbols=${encodeURIComponent(to)}`;
        const response = await fetch(url);

        if (!response.ok) throw new Error("Não foi possível consultar a taxa de câmbio.");

        const data = await response.json();
        const rate = Number(data.rates?.[to]);

        if (!Number.isFinite(rate)) {
            throw new Error("A API não retornou uma taxa para essa moeda.");
        }

        const converted = amount * rate;
        $("#conversionValue").textContent = new Intl.NumberFormat("pt-BR", {
            style: "currency",
            currency: to
        }).format(converted);

        $("#conversionRate").textContent =
            `1 ${from} = ${new Intl.NumberFormat("pt-BR", {
                maximumFractionDigits: 6
            }).format(rate)} ${to}`;

        $("#conversionLabel").textContent =
            `${new Intl.NumberFormat("pt-BR", {
                maximumFractionDigits: 2
            }).format(amount)} ${from} convertido`;

        $("#conversionDate").textContent =
            data.date ? `Taxa de referência: ${data.date}` : "Taxa de referência";

        message("#converterMessage", "Taxa consultada com sucesso.", "success");
    } catch (error) {
        $("#conversionValue").textContent = "Indisponível";
        message("#converterMessage",
            error.message + " Confira sua conexão com a internet e tente novamente.",
            "error");
    }
}

$("#swapCurrencies").addEventListener("click", () => {
    const from = $("#fromCurrency");
    const to = $("#toCurrency");
    const previous = from.value;

    from.value = to.value;
    to.value = previous;

    convertCurrency();
});

// Metas salvas no navegador.
const GOALS_KEY = "moneta_goals_v1";

function getGoals() {
    try {
        const data = JSON.parse(localStorage.getItem(GOALS_KEY) || "[]");
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

function saveGoals(goals) {
    localStorage.setItem(GOALS_KEY, JSON.stringify(goals));
}

function renderGoals() {
    const goals = getGoals();

    if (!goals.length) {
        $("#goalsList").innerHTML = '<p class="muted">Nenhuma meta criada ainda.</p>';
        return;
    }

    $("#goalsList").innerHTML = goals.map(goal => {
        const percent = Math.min(100, Math.round(goal.saved / goal.target * 100));

        return `
            <article class="goal-card">
                <div class="goal-top">
                    <strong>${escapeHTML(goal.name)}</strong>
                    <span class="goal-percent">${percent}%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width:${percent}%"></div>
                </div>
                <div class="goal-bottom">
                    <span>${money(goal.saved)} guardados</span>
                    <span>Meta: ${money(goal.target)}</span>
                </div>
            </article>
        `;
    }).join("");
}

$("#goalForm").addEventListener("submit", event => {
    event.preventDefault();

    const name = $("#goalName").value.trim();
    const target = Number($("#goalTarget").value);
    const saved = Number($("#goalSaved").value);

    if (!name || target <= 0 || saved < 0) return;

    const goals = getGoals();
    goals.push({
        id: Date.now(),
        name,
        target,
        saved
    });

    saveGoals(goals);
    $("#goalForm").reset();
    $("#goalSaved").value = "0";
    renderGoals();
});

// Inicialização.
async function initialize() {
    $("#transactionDate").value = new Date().toLocaleDateString("en-CA");
    renderGoals();
    setAuthMode(false);
    $("#logoutBtn").classList.add("hidden");

    try {
        const data = await api("me.php");
        const user = data.user || data;

        if (user && user.id) {
            showDashboard(user);
        } else {
            showAuth();
        }
    } catch {
        showAuth();
    }

    convertCurrency();
}

initialize();
