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

/* home.twig */
class __TwigTemplate_75397b158f85d82f3d166e91998f14c6 extends Template
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
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["title"] ?? null), "html", null, true);
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
        yield "    <section id=\"hero\">
        <div class=\"container hero-wrapper\">
            <div class=\"hero-left\">
                <h1 class=\"max-w-md\">Apply for your new home loan in minutes</h1>
                <p class=\"max-w-sm\">\"Manage contains all the information I\x27ve learned over almost 15 years of buying, selling, and managing property investments.\"</p>
                <p class=\"max-w-sm\">Manage makes it simple for you to apply and get approved for your home or commercial loan now.\"</p>
                <p class=\"max-w-sm\">- Katniss Everdeen, Director at Manage</p>
                <div>
                    <a href=\"/apply\" class=\"btn btn-primary\">Get Started</a>
                </div>
            </div>
            <div style=\"width: 100%; max-width: 500px;\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Profits Illustration\">
            </div>
        </div>
    </section>

    <section id=\"features\">
        <div class=\"container features-wrapper\">
            <div class=\"features-left\">
                <h2 class=\"max-w-md\">What\x27s different with Manage?</h2>
                <p class=\"max-w-sm\">Manage helps you apply for your home or commercial property loan in minutes.</p>
                <p class=\"max-w-sm\">Fill in your details and we will put you in touch with a top-notch bond originator who will get you the best bond deal for your needs.</p>
            </div>
            <div class=\"features-list\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">01</div>
                        <h3 class=\"feature-title\">Bond approval within days</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Let our originators do the hard work for you and they will contact you back with a bond offer.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">02</div>
                        <h3 class=\"feature-title\">Best rates for your loan</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">When our originators negotiate your loan terms, you can rest assured that we will get you the best rate possible. We know what the banks are looking for so we can put your application in the best light to get you the most favorable interest rates.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">03</div>
                        <h3 class=\"feature-title\">Paid for by the bank</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Our originators are paid a commission on completion of a successful transaction by the bank. We shop your bond around for you to the major banks and they carry the costs of doing business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"testimonials\">
        <div class=\"container\">
            <h2>What Customers Say About Manage</h2>
            <div class=\"testimonials-grid\">
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-anisha.png\" alt=\"Anisha\" class=\"testimonial-avatar\">
                    <h5>Anisha Jones</h5>
                    <p>\"Manage has made it easy to get our bond approved. Thank you Manage for your help!\"</p>
                </div>
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-ali.png\" alt=\"Ali\" class=\"testimonial-avatar\">
                    <h5>Ali Watkins</h5>
                    <p>\"Dealing with manage has been only a pleasure. My bond got approved in days and the consultant was very helpful and professional.\"</p>
                </div>
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-richard.png\" alt=\"Richard\" class=\"testimonial-avatar\">
                    <h5>Richard Able</h5>
                    <p>\"Manage helped our company buy the commercial building we were renting. The process was effortless and their consultant always kept us in the loop.\"</p>
                </div>
            </div>
        </div>
    </section>

    ";
        // line 87
        yield from $this->load("partials/cta.twig", 87)->unwrap()->yield($context);
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "home.twig";
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
        return array (  153 => 87,  70 => 6,  63 => 5,  52 => 3,  41 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("{% extends \x27layouts/main.twig\x27 %}

{% block title %}{{ title }}{% endblock %}

{% block content %}
    <section id=\"hero\">
        <div class=\"container hero-wrapper\">
            <div class=\"hero-left\">
                <h1 class=\"max-w-md\">Apply for your new home loan in minutes</h1>
                <p class=\"max-w-sm\">\"Manage contains all the information I\x27ve learned over almost 15 years of buying, selling, and managing property investments.\"</p>
                <p class=\"max-w-sm\">Manage makes it simple for you to apply and get approved for your home or commercial loan now.\"</p>
                <p class=\"max-w-sm\">- Katniss Everdeen, Director at Manage</p>
                <div>
                    <a href=\"/apply\" class=\"btn btn-primary\">Get Started</a>
                </div>
            </div>
            <div style=\"width: 100%; max-width: 500px;\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Profits Illustration\">
            </div>
        </div>
    </section>

    <section id=\"features\">
        <div class=\"container features-wrapper\">
            <div class=\"features-left\">
                <h2 class=\"max-w-md\">What\x27s different with Manage?</h2>
                <p class=\"max-w-sm\">Manage helps you apply for your home or commercial property loan in minutes.</p>
                <p class=\"max-w-sm\">Fill in your details and we will put you in touch with a top-notch bond originator who will get you the best bond deal for your needs.</p>
            </div>
            <div class=\"features-list\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">01</div>
                        <h3 class=\"feature-title\">Bond approval within days</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Let our originators do the hard work for you and they will contact you back with a bond offer.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">02</div>
                        <h3 class=\"feature-title\">Best rates for your loan</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">When our originators negotiate your loan terms, you can rest assured that we will get you the best rate possible. We know what the banks are looking for so we can put your application in the best light to get you the most favorable interest rates.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">03</div>
                        <h3 class=\"feature-title\">Paid for by the bank</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Our originators are paid a commission on completion of a successful transaction by the bank. We shop your bond around for you to the major banks and they carry the costs of doing business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"testimonials\">
        <div class=\"container\">
            <h2>What Customers Say About Manage</h2>
            <div class=\"testimonials-grid\">
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-anisha.png\" alt=\"Anisha\" class=\"testimonial-avatar\">
                    <h5>Anisha Jones</h5>
                    <p>\"Manage has made it easy to get our bond approved. Thank you Manage for your help!\"</p>
                </div>
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-ali.png\" alt=\"Ali\" class=\"testimonial-avatar\">
                    <h5>Ali Watkins</h5>
                    <p>\"Dealing with manage has been only a pleasure. My bond got approved in days and the consultant was very helpful and professional.\"</p>
                </div>
                <div class=\"testimonial-card\">
                    <img src=\"/img/avatar-richard.png\" alt=\"Richard\" class=\"testimonial-avatar\">
                    <h5>Richard Able</h5>
                    <p>\"Manage helped our company buy the commercial building we were renting. The process was effortless and their consultant always kept us in the loop.\"</p>
                </div>
            </div>
        </div>
    </section>

    {% include \x27partials/cta.twig\x27 %}
{% endblock %}", "home.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\home.twig");
    }
}
