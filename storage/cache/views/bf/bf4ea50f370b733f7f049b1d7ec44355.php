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

/* subscribed.twig */
class __TwigTemplate_30f6f1d11547c5dae01efb4ef56c0544 extends Template
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
        yield "Subscription Confirmed | Manage";
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
        yield "<section id=\"hero\" class=\"py-24\">
    <div class=\"container hero-container-centered\">
        
        <div class=\"hero-title-centered\">
            <h1>You\x27re on the List!</h1>
        </div>

        <div class=\"split-text-side text-block-narrow\" style=\"margin: 0 auto;\">
            <p>Thank you for subscribing to our newsletter. You will now be the first to receive insider property secrets, financial planning tips, and the latest marketplace guides directly in your inbox.</p>
            <div style=\"margin-top: 2.5rem; text-align: center;\">
                <a href=\"/\" class=\"btn btn-primary\">Back to Homepage</a>
            </div>
        </div>

    </div>
</section>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "subscribed.twig";
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
        return array (  70 => 6,  63 => 5,  52 => 3,  41 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("{% extends \x27layouts/main.twig\x27 %}

{% block title %}Subscription Confirmed | Manage{% endblock %}

{% block content %}
<section id=\"hero\" class=\"py-24\">
    <div class=\"container hero-container-centered\">
        
        <div class=\"hero-title-centered\">
            <h1>You\x27re on the List!</h1>
        </div>

        <div class=\"split-text-side text-block-narrow\" style=\"margin: 0 auto;\">
            <p>Thank you for subscribing to our newsletter. You will now be the first to receive insider property secrets, financial planning tips, and the latest marketplace guides directly in your inbox.</p>
            <div style=\"margin-top: 2.5rem; text-align: center;\">
                <a href=\"/\" class=\"btn btn-primary\">Back to Homepage</a>
            </div>
        </div>

    </div>
</section>
{% endblock %}", "subscribed.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\subscribed.twig");
    }
}
