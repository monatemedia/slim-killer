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

/* success.twig */
class __TwigTemplate_f4c68ffb6014f2baac39160e48540d1b extends Template
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
        yield "Application Successful | Manage";
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
        <div class=\"split-wrapper\">
            
            <div class=\"split-img-side\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Success Illustration\" style=\"width: 100%; max-width: 500px; display: block; margin: 0 auto;\">
            </div>
            
            <div class=\"split-text-side text-block-narrow\">
                <h2>Application Submitted!</h2>
                <p>Thank you for submitting your bond application. One of our expert bond originators will review your details and contact you within the next 24 to 48 hours to guide you through the best deals available from the banks.</p>
                <div style=\"margin-top: 1.5rem;\">
                    <a href=\"/\" class=\"btn btn-primary\">Return Home</a>
                </div>
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
        return "success.twig";
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

{% block title %}Application Successful | Manage{% endblock %}

{% block content %}
<section id=\"hero\" class=\"py-24\">
    <div class=\"container hero-container-centered\">
        <div class=\"split-wrapper\">
            
            <div class=\"split-img-side\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Success Illustration\" style=\"width: 100%; max-width: 500px; display: block; margin: 0 auto;\">
            </div>
            
            <div class=\"split-text-side text-block-narrow\">
                <h2>Application Submitted!</h2>
                <p>Thank you for submitting your bond application. One of our expert bond originators will review your details and contact you within the next 24 to 48 hours to guide you through the best deals available from the banks.</p>
                <div style=\"margin-top: 1.5rem;\">
                    <a href=\"/\" class=\"btn btn-primary\">Return Home</a>
                </div>
            </div>

        </div>
    </div>
</section>
{% endblock %}", "success.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\success.twig");
    }
}
