<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* calculator.twig */
class __TwigTemplate_ac6d35e84eca67df1981a9bfee12be97 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->blocks = [
            'title' => [$this, 'block_title'],
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doGetParent(array $context): bool|string|Template|TemplateWrapper
    {
        // line 1
        return "layouts/main.twig";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("layouts/main.twig", 1);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        yield "Mortgage Calculator";
        yield from [];
    }

    // line 5
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_content(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 6
        yield "<style>
    /* --- Layout Architecture --- */
    .calc-layout-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    @media (min-width: 1024px) {
        .calc-layout-grid {
            grid-template-columns: 1fr 2fr; /* Aligns sidebar and main section beautifully */
        }
    }

    .calc-sidebar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.75rem;
        height: fit-content;
    }
    .calc-sidebar h2 {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        margin-top: 0;
    }
    .param-group {
        margin-bottom: 1.5rem;
    }
    .param-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }
    .param-value-display {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .param-value-display.accent { color: #dc2626; }
    .param-input {
        width: 100%;
        padding: 0.6rem 0.875rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #0f172a;
        background: #f8fafc;
        transition: border-color 0.15s, background 0.15s;
        box-sizing: border-box;
    }
    .param-input:focus {
        outline: none;
        border-color: #dc2626;
        background: #fff;
    }
    .param-range {
        width: 100%;
        accent-color: #dc2626;
        margin-top: 0.5rem;
        cursor: pointer;
    }
    .range-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    /* --- Metric Cards Layout --- */
    .metric-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    @media (min-width: 640px) {
        .metric-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .metric-card {
        border-radius: 14px;
        padding: 1.25rem 1rem;
    }
    .metric-card.primary {
        background: #0c2340;
        color: #fff;
    }
    .metric-card.secondary {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
    }
    .metric-card.danger {
        background: #fff1f1;
        border: 1px solid #fecaca;
        color: #7f1d1d;
    }
    .metric-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        opacity: 0.6;
        margin-bottom: 0.4rem;
    }
    .metric-card.primary .metric-label { opacity: 0.65; color: #bfdbfe; }
    .metric-card.secondary .metric-label { color: #64748b; opacity: 1; }
    .metric-card.danger .metric-label { color: #b91c1c; opacity: 0.75; }
    .metric-amount {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }
    .metric-card.primary .metric-amount { color: #fff; }
    .metric-card.secondary .metric-amount { color: #0f172a; }
    .metric-card.danger .metric-amount { color: #dc2626; }
    .metric-currency {
        font-size: 0.8rem;
        font-weight: 600;
        opacity: 0.7;
        margin-right: 2px;
    }

    /* --- Breakdown Panels --- */
    .panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
    }
    .panel-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .panel-title .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #dc2626;
        flex-shrink: 0;
    }

    .breakdown-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .breakdown-table th {
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .breakdown-table th:not(:first-child) { text-align: right; }
    .breakdown-table td {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }
    .breakdown-table td:not(:first-child) { text-align: right; }
    .breakdown-table .row-label { font-weight: 600; color: #334155; }
    .breakdown-table .row-value { font-weight: 700; color: #0f172a; }
    .breakdown-table .row-interest { color: #dc2626; font-weight: 600; }
    .breakdown-table .row-principal { color: #059669; font-weight: 600; }

    /* --- Composition Bars --- */
    .visual-bars { margin-bottom: 1.25rem; }
    .bar-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.6rem;
        font-size: 0.8rem;
    }
    .bar-label {
        width: 80px;
        text-align: right;
        color: #64748b;
        font-weight: 500;
        flex-shrink: 0;
    }
    .bar-track {
        flex: 1;
        height: 8px;
        background: #f1f5f9;
        border-radius: 99px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 99px;
        transition: width 0.4s ease;
    }
    .bar-fill.capital { background: #059669; }
    .bar-fill.interest { background: #dc2626; }
    .bar-amount {
        width: 90px;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    /* --- Amortization Toggle & Layout --- */
    .amortization-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
    }
    .toggle-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 700;
        color: #64748b;
        transition: background 0.15s;
    }
    .amortization-toggle:hover .toggle-icon { background: #e2e8f0; }

    .amort-table-wrap {
        margin-top: 1.25rem;
        overflow-y: auto;
        max-height: 360px;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
    }
    .amort-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .amort-table thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        padding: 0.6rem 0.75rem;
        text-align: right;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        border-bottom: 1px solid #e2e8f0;
        z-index: 5;
    }
    .amort-table thead th:first-child,
    .amort-table thead th:nth-child(2) { text-align: left; }
    .amort-table tbody td {
        padding: 0.55rem 0.75rem;
        text-align: right;
        border-bottom: 1px solid #f8fafc;
        color: #334155;
    }
    .amort-table tbody td:first-child {
        text-align: left;
        font-weight: 700;
        color: #0f172a;
    }
    .amort-table tbody td:nth-child(2) {
        text-align: left;
        color: #64748b;
    }
    .amort-table tbody tr:hover td { background: #fafafa; }
    .amort-table .td-interest { color: #dc2626; }
    .amort-table .td-principal { color: #059669; }
    .amort-table .td-balance { font-weight: 600; color: #0f172a; }
</style>

<section class=\"container\" style=\"padding-top: 2rem; padding-bottom: 4rem;\" x-data=\"mortgageCalculator()\">

    <div style=\"text-align: center; margin-bottom: 2.5rem;\">
        <h1 style=\"font-size: 2.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;\">Mortgage Calculator</h1>
        <p style=\"color: #64748b; max-width: 32rem; margin: 0 auto; line-height: 1.6;\">
            Work out your <strong style=\"color: #334155;\">monthly payment</strong> and <em style=\"color: #334155;\">total cost of credit</em> on a home or business loan.
        </p>
    </div>

    <div class=\"calc-layout-grid\">

        ";
        // line 311
        yield "        <div class=\"calc-sidebar\">
            <h2>Loan Parameters</h2>

            <div class=\"param-group\">
                <label class=\"param-label\">Loan Amount</label>
                <input type=\"number\" x-model.number=\"loanAmount\" min=\"50000\" max=\"50000000\" step=\"10000\" class=\"param-input\" />
                <input type=\"range\" x-model.number=\"loanAmount\" min=\"50000\" max=\"10000000\" step=\"50000\" class=\"param-range\">
                <div class=\"range-labels\"><span>R 50k</span><span>R 10m</span></div>
            </div>

            <div class=\"param-group\">
                <label class=\"param-label\">Interest Rate</label>
                <input type=\"number\" x-model.number=\"interestRate\" min=\"0\" max=\"20\" step=\"0.25\" class=\"param-input\" />
                <input type=\"range\" x-model.number=\"interestRate\" min=\"5\" max=\"15\" step=\"0.25\" class=\"param-range\">
                <div class=\"range-labels\"><span>5%</span><span>15%</span></div>
            </div>

            <div class=\"param-group\">
                <label class=\"param-label\">
                    Loan Term &nbsp;
                    <span class=\"param-value-display accent\" x-text=\"years + \x27 yrs\x27\"></span>
                </label>
                <input type=\"range\" x-model.number=\"years\" min=\"5\" max=\"30\" step=\"1\" class=\"param-range\" style=\"margin-top:0;\">
                <div class=\"range-labels\"><span>5 years</span><span>30 years</span></div>
            </div>

            <div class=\"param-group\" style=\"margin-bottom:0;\">
                <label class=\"param-label\">Purchase Date</label>
                <input type=\"date\" x-model=\"startDate\" class=\"param-input\" style=\"font-weight:400;\" />
            </div>
        </div>

        ";
        // line 344
        yield "        <div>

            ";
        // line 347
        yield "            <div class=\"metric-grid\">
                <div class=\"metric-card primary\">
                    <div class=\"metric-label\">Monthly payment</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(monthlyPayment)\">0</span>
                    </div>
                </div>
                <div class=\"metric-card secondary\">
                    <div class=\"metric-label\">Cumulative interest</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(totalInterest)\">0</span>
                    </div>
                </div>
                <div class=\"metric-card danger\">
                    <div class=\"metric-label\">Total repayment</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(totalRepayment)\">0</span>
                    </div>
                </div>
            </div>

            ";
        // line 369
        yield "            <div class=\"panel\">
                <div class=\"panel-title\"><span class=\"dot\"></span> Repayment composition</div>

                <div class=\"visual-bars\">
                    <div class=\"bar-row\">
                        <span class=\"bar-label\">Capital</span>
                        <div class=\"bar-track\">
                            <div class=\"bar-fill capital\" :style=\"\x27width:\x27 + capitalPct + \x27%\x27\"></div>
                        </div>
                        <span class=\"bar-amount\">
                            <span x-text=\"capitalPct.toFixed(1)\"></span>%
                        </span>
                    </div>
                    <div class=\"bar-row\">
                        <span class=\"bar-label\">Interest</span>
                        <div class=\"bar-track\">
                            <div class=\"bar-fill interest\" :style=\"\x27width:\x27 + interestPct + \x27%\x27\"></div>
                        </div>
                        <span class=\"bar-amount\">
                            <span x-text=\"interestPct.toFixed(1)\"></span>%
                        </span>
                    </div>
                </div>

                <table class=\"breakdown-table\">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>First month</th>
                            <th>Final month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class=\"row-label\">Monthly installment</td>
                            <td class=\"row-value\">R <span x-text=\"formatNumber(monthlyPayment)\">0</span></td>
                            <td class=\"row-value\">R <span x-text=\"formatNumber(monthlyPayment)\">0</span></td>
                        </tr>
                        <tr>
                            <td class=\"row-interest\">Interest portion</td>
                            <td class=\"row-interest\">R <span x-text=\"formatNumber(firstMonthInterest)\">0</span></td>
                            <td class=\"row-interest\">R <span x-text=\"formatNumber(finalMonthInterest)\">0</span></td>
                        </tr>
                        <tr>
                            <td class=\"row-principal\">Principal (capital)</td>
                            <td class=\"row-principal\">R <span x-text=\"formatNumber(firstMonthPrincipal)\">0</span></td>
                            <td class=\"row-principal\">R <span x-text=\"formatNumber(finalMonthPrincipal)\">0</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            ";
        // line 422
        yield "            <div class=\"panel\" x-data=\"{ openTable: false }\">
                <div class=\"amortization-toggle\" @click=\"openTable = !openTable\">
                    <div class=\"panel-title\" style=\"margin-bottom:0;\">
                        <span class=\"dot\"></span> Full amortization schedule
                        <span style=\"font-size: 0.75rem; font-weight: 400; color: #94a3b8; margin-left: 0.25rem;\" x-text=\"\x27(\x27 + schedule.length + \x27 payments)\x27\"></span>
                    </div>
                    <div class=\"toggle-icon\" x-text=\"openTable ? \x27−\x27 : \x27+\x27\"></div>
                </div>

                <div x-show=\"openTable\" x-transition class=\"amort-table-wrap\">
                    <table class=\"amort-table\">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for=\"row in schedule\" :key=\"row.period\">
                                <tr>
                                    <td x-text=\"row.period\"></td>
                                    <td x-text=\"row.date\"></td>
                                    <td>R <span x-text=\"formatNumber(row.payment)\"></span></td>
                                    <td class=\"td-principal\">R <span x-text=\"formatNumber(row.principalPaid)\"></span></td>
                                    <td class=\"td-interest\">R <span x-text=\"formatNumber(row.interestPaid)\"></span></td>
                                    <td class=\"td-balance\">R <span x-text=\"formatNumber(row.balance)\"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</section>

";
        // line 463
        yield from $this->load("partials/cta.twig", 463)->unwrap()->yield($context);
        // line 464
        yield "
<script>
function mortgageCalculator() {
    return {
        loanAmount: 1250000,
        interestRate: 9.5,
        years: 20,
        startDate: new Date().toISOString().split(\x27T\x27)[0],

        monthlyPayment: 0,
        totalInterest: 0,
        totalRepayment: 0,
        capitalPct: 0,
        interestPct: 0,
        firstMonthInterest: 0,
        finalMonthInterest: 0,
        firstMonthPrincipal: 0,
        finalMonthPrincipal: 0,
        schedule: [],

        init() {
            this.\$watch(\x27loanAmount\x27, () => this.calculate());
            this.\$watch(\x27interestRate\x27, () => this.calculate());
            this.\$watch(\x27years\x27, () => this.calculate());
            this.\$watch(\x27startDate\x27, () => this.calculate());
            this.calculate();
        },

        calculate() {
            const P = this.loanAmount;
            const r = (this.interestRate / 100) / 12;
            const n = this.years * 12;

            if (r === 0) {
                this.monthlyPayment = P / n;
            } else {
                this.monthlyPayment = P * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            }

            this.totalRepayment = this.monthlyPayment * n;
            this.totalInterest = this.totalRepayment - P;

            this.capitalPct = (P / this.totalRepayment) * 100;
            this.interestPct = (this.totalInterest / this.totalRepayment) * 100;

            let currentBalance = P;
            let tempSchedule = [];
            let baseDate = new Date(this.startDate);

            for (let i = 1; i <= n; i++) {
                let interestPortion = currentBalance * r;
                let principalPortion = this.monthlyPayment - interestPortion;

                if (principalPortion > currentBalance) {
                    principalPortion = currentBalance;
                }

                currentBalance -= principalPortion;
                if (currentBalance < 0.01) currentBalance = 0;

                let payDate = new Date(baseDate.getFullYear(), baseDate.getMonth() + i - 1, 1);
                let dateString = payDate.toLocaleDateString(\x27en-ZA\x27, { year: \x27numeric\x27, month: \x27short\x27 });

                tempSchedule.push({
                    period: i,
                    date: dateString,
                    payment: this.monthlyPayment,
                    principalPaid: principalPortion,
                    interestPaid: interestPortion,
                    balance: currentBalance
                });
            }

            this.schedule = tempSchedule;

            if (this.schedule.length > 0) {
                this.firstMonthInterest = this.schedule[0].interestPaid;
                this.firstMonthPrincipal = this.schedule[0].balance === 0 && n === 1 ? P : this.schedule[0].principalPaid;
                this.finalMonthInterest = this.schedule[this.schedule.length - 1].interestPaid;
                this.finalMonthPrincipal = this.schedule[this.schedule.length - 1].principalPaid;
            }
        },

        formatNumber(val) {
            return Number(val).toLocaleString(\x27en-ZA\x27, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "calculator.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  536 => 464,  534 => 463,  491 => 422,  437 => 369,  414 => 347,  410 => 344,  376 => 311,  70 => 6,  63 => 5,  52 => 3,  41 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("{% extends \x27layouts/main.twig\x27 %}

{% block title %}Mortgage Calculator{% endblock %}

{% block content %}
<style>
    /* --- Layout Architecture --- */
    .calc-layout-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin-top: 2rem;
    }
    
    @media (min-width: 1024px) {
        .calc-layout-grid {
            grid-template-columns: 1fr 2fr; /* Aligns sidebar and main section beautifully */
        }
    }

    .calc-sidebar {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.75rem;
        height: fit-content;
    }
    .calc-sidebar h2 {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        margin-top: 0;
    }
    .param-group {
        margin-bottom: 1.5rem;
    }
    .param-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 0.5rem;
    }
    .param-value-display {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .param-value-display.accent { color: #dc2626; }
    .param-input {
        width: 100%;
        padding: 0.6rem 0.875rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #0f172a;
        background: #f8fafc;
        transition: border-color 0.15s, background 0.15s;
        box-sizing: border-box;
    }
    .param-input:focus {
        outline: none;
        border-color: #dc2626;
        background: #fff;
    }
    .param-range {
        width: 100%;
        accent-color: #dc2626;
        margin-top: 0.5rem;
        cursor: pointer;
    }
    .range-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        color: #94a3b8;
        margin-top: 0.25rem;
    }

    /* --- Metric Cards Layout --- */
    .metric-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    @media (min-width: 640px) {
        .metric-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    .metric-card {
        border-radius: 14px;
        padding: 1.25rem 1rem;
    }
    .metric-card.primary {
        background: #0c2340;
        color: #fff;
    }
    .metric-card.secondary {
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
    }
    .metric-card.danger {
        background: #fff1f1;
        border: 1px solid #fecaca;
        color: #7f1d1d;
    }
    .metric-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        opacity: 0.6;
        margin-bottom: 0.4rem;
    }
    .metric-card.primary .metric-label { opacity: 0.65; color: #bfdbfe; }
    .metric-card.secondary .metric-label { color: #64748b; opacity: 1; }
    .metric-card.danger .metric-label { color: #b91c1c; opacity: 0.75; }
    .metric-amount {
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }
    .metric-card.primary .metric-amount { color: #fff; }
    .metric-card.secondary .metric-amount { color: #0f172a; }
    .metric-card.danger .metric-amount { color: #dc2626; }
    .metric-currency {
        font-size: 0.8rem;
        font-weight: 600;
        opacity: 0.7;
        margin-right: 2px;
    }

    /* --- Breakdown Panels --- */
    .panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
    }
    .panel-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .panel-title .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #dc2626;
        flex-shrink: 0;
    }

    .breakdown-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .breakdown-table th {
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .breakdown-table th:not(:first-child) { text-align: right; }
    .breakdown-table td {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f8fafc;
        vertical-align: middle;
    }
    .breakdown-table td:not(:first-child) { text-align: right; }
    .breakdown-table .row-label { font-weight: 600; color: #334155; }
    .breakdown-table .row-value { font-weight: 700; color: #0f172a; }
    .breakdown-table .row-interest { color: #dc2626; font-weight: 600; }
    .breakdown-table .row-principal { color: #059669; font-weight: 600; }

    /* --- Composition Bars --- */
    .visual-bars { margin-bottom: 1.25rem; }
    .bar-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.6rem;
        font-size: 0.8rem;
    }
    .bar-label {
        width: 80px;
        text-align: right;
        color: #64748b;
        font-weight: 500;
        flex-shrink: 0;
    }
    .bar-track {
        flex: 1;
        height: 8px;
        background: #f1f5f9;
        border-radius: 99px;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 99px;
        transition: width 0.4s ease;
    }
    .bar-fill.capital { background: #059669; }
    .bar-fill.interest { background: #dc2626; }
    .bar-amount {
        width: 90px;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    /* --- Amortization Toggle & Layout --- */
    .amortization-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        user-select: none;
    }
    .toggle-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 700;
        color: #64748b;
        transition: background 0.15s;
    }
    .amortization-toggle:hover .toggle-icon { background: #e2e8f0; }

    .amort-table-wrap {
        margin-top: 1.25rem;
        overflow-y: auto;
        max-height: 360px;
        border: 1px solid #f1f5f9;
        border-radius: 10px;
    }
    .amort-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .amort-table thead th {
        position: sticky;
        top: 0;
        background: #f8fafc;
        padding: 0.6rem 0.75rem;
        text-align: right;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        border-bottom: 1px solid #e2e8f0;
        z-index: 5;
    }
    .amort-table thead th:first-child,
    .amort-table thead th:nth-child(2) { text-align: left; }
    .amort-table tbody td {
        padding: 0.55rem 0.75rem;
        text-align: right;
        border-bottom: 1px solid #f8fafc;
        color: #334155;
    }
    .amort-table tbody td:first-child {
        text-align: left;
        font-weight: 700;
        color: #0f172a;
    }
    .amort-table tbody td:nth-child(2) {
        text-align: left;
        color: #64748b;
    }
    .amort-table tbody tr:hover td { background: #fafafa; }
    .amort-table .td-interest { color: #dc2626; }
    .amort-table .td-principal { color: #059669; }
    .amort-table .td-balance { font-weight: 600; color: #0f172a; }
</style>

<section class=\"container\" style=\"padding-top: 2rem; padding-bottom: 4rem;\" x-data=\"mortgageCalculator()\">

    <div style=\"text-align: center; margin-bottom: 2.5rem;\">
        <h1 style=\"font-size: 2.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;\">Mortgage Calculator</h1>
        <p style=\"color: #64748b; max-width: 32rem; margin: 0 auto; line-height: 1.6;\">
            Work out your <strong style=\"color: #334155;\">monthly payment</strong> and <em style=\"color: #334155;\">total cost of credit</em> on a home or business loan.
        </p>
    </div>

    <div class=\"calc-layout-grid\">

        {# Sidebar: Parameters #}
        <div class=\"calc-sidebar\">
            <h2>Loan Parameters</h2>

            <div class=\"param-group\">
                <label class=\"param-label\">Loan Amount</label>
                <input type=\"number\" x-model.number=\"loanAmount\" min=\"50000\" max=\"50000000\" step=\"10000\" class=\"param-input\" />
                <input type=\"range\" x-model.number=\"loanAmount\" min=\"50000\" max=\"10000000\" step=\"50000\" class=\"param-range\">
                <div class=\"range-labels\"><span>R 50k</span><span>R 10m</span></div>
            </div>

            <div class=\"param-group\">
                <label class=\"param-label\">Interest Rate</label>
                <input type=\"number\" x-model.number=\"interestRate\" min=\"0\" max=\"20\" step=\"0.25\" class=\"param-input\" />
                <input type=\"range\" x-model.number=\"interestRate\" min=\"5\" max=\"15\" step=\"0.25\" class=\"param-range\">
                <div class=\"range-labels\"><span>5%</span><span>15%</span></div>
            </div>

            <div class=\"param-group\">
                <label class=\"param-label\">
                    Loan Term &nbsp;
                    <span class=\"param-value-display accent\" x-text=\"years + \x27 yrs\x27\"></span>
                </label>
                <input type=\"range\" x-model.number=\"years\" min=\"5\" max=\"30\" step=\"1\" class=\"param-range\" style=\"margin-top:0;\">
                <div class=\"range-labels\"><span>5 years</span><span>30 years</span></div>
            </div>

            <div class=\"param-group\" style=\"margin-bottom:0;\">
                <label class=\"param-label\">Purchase Date</label>
                <input type=\"date\" x-model=\"startDate\" class=\"param-input\" style=\"font-weight:400;\" />
            </div>
        </div>

        {# Main Content Area #}
        <div>

            {# Summary Metric Cards #}
            <div class=\"metric-grid\">
                <div class=\"metric-card primary\">
                    <div class=\"metric-label\">Monthly payment</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(monthlyPayment)\">0</span>
                    </div>
                </div>
                <div class=\"metric-card secondary\">
                    <div class=\"metric-label\">Cumulative interest</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(totalInterest)\">0</span>
                    </div>
                </div>
                <div class=\"metric-card danger\">
                    <div class=\"metric-label\">Total repayment</div>
                    <div class=\"metric-amount\">
                        <span class=\"metric-currency\">R</span><span x-text=\"formatNumber(totalRepayment)\">0</span>
                    </div>
                </div>
            </div>

            {# Visual Repayment Split #}
            <div class=\"panel\">
                <div class=\"panel-title\"><span class=\"dot\"></span> Repayment composition</div>

                <div class=\"visual-bars\">
                    <div class=\"bar-row\">
                        <span class=\"bar-label\">Capital</span>
                        <div class=\"bar-track\">
                            <div class=\"bar-fill capital\" :style=\"\x27width:\x27 + capitalPct + \x27%\x27\"></div>
                        </div>
                        <span class=\"bar-amount\">
                            <span x-text=\"capitalPct.toFixed(1)\"></span>%
                        </span>
                    </div>
                    <div class=\"bar-row\">
                        <span class=\"bar-label\">Interest</span>
                        <div class=\"bar-track\">
                            <div class=\"bar-fill interest\" :style=\"\x27width:\x27 + interestPct + \x27%\x27\"></div>
                        </div>
                        <span class=\"bar-amount\">
                            <span x-text=\"interestPct.toFixed(1)\"></span>%
                        </span>
                    </div>
                </div>

                <table class=\"breakdown-table\">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>First month</th>
                            <th>Final month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class=\"row-label\">Monthly installment</td>
                            <td class=\"row-value\">R <span x-text=\"formatNumber(monthlyPayment)\">0</span></td>
                            <td class=\"row-value\">R <span x-text=\"formatNumber(monthlyPayment)\">0</span></td>
                        </tr>
                        <tr>
                            <td class=\"row-interest\">Interest portion</td>
                            <td class=\"row-interest\">R <span x-text=\"formatNumber(firstMonthInterest)\">0</span></td>
                            <td class=\"row-interest\">R <span x-text=\"formatNumber(finalMonthInterest)\">0</span></td>
                        </tr>
                        <tr>
                            <td class=\"row-principal\">Principal (capital)</td>
                            <td class=\"row-principal\">R <span x-text=\"formatNumber(firstMonthPrincipal)\">0</span></td>
                            <td class=\"row-principal\">R <span x-text=\"formatNumber(finalMonthPrincipal)\">0</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {# Amortization Table #}
            <div class=\"panel\" x-data=\"{ openTable: false }\">
                <div class=\"amortization-toggle\" @click=\"openTable = !openTable\">
                    <div class=\"panel-title\" style=\"margin-bottom:0;\">
                        <span class=\"dot\"></span> Full amortization schedule
                        <span style=\"font-size: 0.75rem; font-weight: 400; color: #94a3b8; margin-left: 0.25rem;\" x-text=\"\x27(\x27 + schedule.length + \x27 payments)\x27\"></span>
                    </div>
                    <div class=\"toggle-icon\" x-text=\"openTable ? \x27−\x27 : \x27+\x27\"></div>
                </div>

                <div x-show=\"openTable\" x-transition class=\"amort-table-wrap\">
                    <table class=\"amort-table\">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Payment</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for=\"row in schedule\" :key=\"row.period\">
                                <tr>
                                    <td x-text=\"row.period\"></td>
                                    <td x-text=\"row.date\"></td>
                                    <td>R <span x-text=\"formatNumber(row.payment)\"></span></td>
                                    <td class=\"td-principal\">R <span x-text=\"formatNumber(row.principalPaid)\"></span></td>
                                    <td class=\"td-interest\">R <span x-text=\"formatNumber(row.interestPaid)\"></span></td>
                                    <td class=\"td-balance\">R <span x-text=\"formatNumber(row.balance)\"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</section>

{% include \x27partials/cta.twig\x27 %}

<script>
function mortgageCalculator() {
    return {
        loanAmount: 1250000,
        interestRate: 9.5,
        years: 20,
        startDate: new Date().toISOString().split(\x27T\x27)[0],

        monthlyPayment: 0,
        totalInterest: 0,
        totalRepayment: 0,
        capitalPct: 0,
        interestPct: 0,
        firstMonthInterest: 0,
        finalMonthInterest: 0,
        firstMonthPrincipal: 0,
        finalMonthPrincipal: 0,
        schedule: [],

        init() {
            this.\$watch(\x27loanAmount\x27, () => this.calculate());
            this.\$watch(\x27interestRate\x27, () => this.calculate());
            this.\$watch(\x27years\x27, () => this.calculate());
            this.\$watch(\x27startDate\x27, () => this.calculate());
            this.calculate();
        },

        calculate() {
            const P = this.loanAmount;
            const r = (this.interestRate / 100) / 12;
            const n = this.years * 12;

            if (r === 0) {
                this.monthlyPayment = P / n;
            } else {
                this.monthlyPayment = P * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
            }

            this.totalRepayment = this.monthlyPayment * n;
            this.totalInterest = this.totalRepayment - P;

            this.capitalPct = (P / this.totalRepayment) * 100;
            this.interestPct = (this.totalInterest / this.totalRepayment) * 100;

            let currentBalance = P;
            let tempSchedule = [];
            let baseDate = new Date(this.startDate);

            for (let i = 1; i <= n; i++) {
                let interestPortion = currentBalance * r;
                let principalPortion = this.monthlyPayment - interestPortion;

                if (principalPortion > currentBalance) {
                    principalPortion = currentBalance;
                }

                currentBalance -= principalPortion;
                if (currentBalance < 0.01) currentBalance = 0;

                let payDate = new Date(baseDate.getFullYear(), baseDate.getMonth() + i - 1, 1);
                let dateString = payDate.toLocaleDateString(\x27en-ZA\x27, { year: \x27numeric\x27, month: \x27short\x27 });

                tempSchedule.push({
                    period: i,
                    date: dateString,
                    payment: this.monthlyPayment,
                    principalPaid: principalPortion,
                    interestPaid: interestPortion,
                    balance: currentBalance
                });
            }

            this.schedule = tempSchedule;

            if (this.schedule.length > 0) {
                this.firstMonthInterest = this.schedule[0].interestPaid;
                this.firstMonthPrincipal = this.schedule[0].balance === 0 && n === 1 ? P : this.schedule[0].principalPaid;
                this.finalMonthInterest = this.schedule[this.schedule.length - 1].interestPaid;
                this.finalMonthPrincipal = this.schedule[this.schedule.length - 1].principalPaid;
            }
        },

        formatNumber(val) {
            return Number(val).toLocaleString(\x27en-ZA\x27, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    }
}
</script>
{% endblock %}", "calculator.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\calculator.twig");
    }
}
