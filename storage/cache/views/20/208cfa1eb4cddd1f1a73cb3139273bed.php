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

/* partials/header.twig */
class __TwigTemplate_2cd5b7bc4461bb2dfc3c7a1c90af4a7c extends Template
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

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield "<header>
    <nav class=\"container\">
        <div class=\"nav-wrapper\">
            <a href=\"/\">
                <img src=\"/img/logo.svg\" alt=\"Logo\">
            </a>
            
            <div class=\"nav-links\">
                <a href=\"/\">Home</a>
                <a href=\"/calculator\">Calculator</a>
                <a href=\"/buyers-guide\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\">Property Secrets</a>
            </div>

            <a href=\"/apply\" class=\"btn btn-primary nav-cta\">Apply Now</a>

            <button @click=\"mobileMenuOpen = !mobileMenuOpen\" class=\"hamburger\">
                <span class=\"hamburger-top\"></span>
                <span class=\"hamburger-middle\"></span>
                <span class=\"hamburger-bottom\"></span>
            </button>
        </div>

        <div x-show=\"mobileMenuOpen\" x-transition class=\"mobile-menu-container\">
            <div id=\"menu\" class=\"mobile-menu-wrapper\">
                <a href=\"/\" @click=\"mobileMenuOpen = false\">Home</a>
                <a href=\"/calculator\" @click=\"mobileMenuOpen = false\">Calculator</a>
                <a href=\"/buyers-guide\" @click=\"mobileMenuOpen = false\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\" @click=\"mobileMenuOpen = false\">Property Secrets</a>
                <a href=\"/apply\" class=\"btn btn-primary mobile-menu-cta\" @click=\"mobileMenuOpen = false\">Apply Now</a>
            </div>
        </div>
    </nav>
</header>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "partials/header.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  42 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("<header>
    <nav class=\"container\">
        <div class=\"nav-wrapper\">
            <a href=\"/\">
                <img src=\"/img/logo.svg\" alt=\"Logo\">
            </a>
            
            <div class=\"nav-links\">
                <a href=\"/\">Home</a>
                <a href=\"/calculator\">Calculator</a>
                <a href=\"/buyers-guide\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\">Property Secrets</a>
            </div>

            <a href=\"/apply\" class=\"btn btn-primary nav-cta\">Apply Now</a>

            <button @click=\"mobileMenuOpen = !mobileMenuOpen\" class=\"hamburger\">
                <span class=\"hamburger-top\"></span>
                <span class=\"hamburger-middle\"></span>
                <span class=\"hamburger-bottom\"></span>
            </button>
        </div>

        <div x-show=\"mobileMenuOpen\" x-transition class=\"mobile-menu-container\">
            <div id=\"menu\" class=\"mobile-menu-wrapper\">
                <a href=\"/\" @click=\"mobileMenuOpen = false\">Home</a>
                <a href=\"/calculator\" @click=\"mobileMenuOpen = false\">Calculator</a>
                <a href=\"/buyers-guide\" @click=\"mobileMenuOpen = false\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\" @click=\"mobileMenuOpen = false\">Property Secrets</a>
                <a href=\"/apply\" class=\"btn btn-primary mobile-menu-cta\" @click=\"mobileMenuOpen = false\">Apply Now</a>
            </div>
        </div>
    </nav>
</header>", "partials/header.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\partials\\header.twig");
    }
}
