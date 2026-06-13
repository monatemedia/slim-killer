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

/* layouts/main.twig */
class __TwigTemplate_8d1d7dd70e10d55cbfbd84f572e18ba1 extends Template
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
            'title' => [$this, 'block_title'],
            'styles' => [$this, 'block_styles'],
            'content' => [$this, 'block_content'],
            'scripts' => [$this, 'block_scripts'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 1
        yield "<!DOCTYPE html>
<html lang=\"en\" x-data=\"{ mobileMenuOpen: false, showTopButton: false }\" 
      @scroll.window=\"showTopButton = (window.pageYOffset > 200)\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <link rel=\"stylesheet\" href=\"/css/main.css\">
    <title>";
        // line 8
        yield from $this->unwrap()->yieldBlock('title', $context, $blocks);
        yield "</title>
    
    <script defer src=\"/js/alpine.min.js\"></script>
    
    ";
        // line 12
        yield from $this->unwrap()->yieldBlock('styles', $context, $blocks);
        // line 13
        yield "</head>
<body>
    <div class=\"hero-pattern-bg-wrapper\">
        ";
        // line 16
        yield from $this->load("partials/header.twig", 16)->unwrap()->yield($context);
        // line 17
        yield "    
        <main>
            ";
        // line 19
        yield from $this->unwrap()->yieldBlock('content', $context, $blocks);
        // line 20
        yield "        </main>

        <button x-show=\"showTopButton\" @click=\"window.scrollTo({top: 0, behavior: \x27smooth\x27})\" x-transition class=\"scroll-top-btn\">
            <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\" stroke-width=\"3\">
                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M5 15l7-7 7 7\" />
            </svg>
        </button>

        ";
        // line 28
        yield from $this->load("partials/footer.twig", 28)->unwrap()->yield($context);
        // line 29
        yield "
    </div>

    ";
        // line 32
        yield from $this->unwrap()->yieldBlock('scripts', $context, $blocks);
        // line 33
        yield "</body>
</html>";
        yield from [];
    }

    // line 8
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        yield "Manage Home";
        yield from [];
    }

    // line 12
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_styles(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        yield from [];
    }

    // line 19
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_content(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        yield from [];
    }

    // line 32
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_scripts(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "layouts/main.twig";
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
        return array (  133 => 32,  123 => 19,  113 => 12,  102 => 8,  96 => 33,  94 => 32,  89 => 29,  87 => 28,  77 => 20,  75 => 19,  71 => 17,  69 => 16,  64 => 13,  62 => 12,  55 => 8,  46 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("<!DOCTYPE html>
<html lang=\"en\" x-data=\"{ mobileMenuOpen: false, showTopButton: false }\" 
      @scroll.window=\"showTopButton = (window.pageYOffset > 200)\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <link rel=\"stylesheet\" href=\"/css/main.css\">
    <title>{% block title %}Manage Home{% endblock %}</title>
    
    <script defer src=\"/js/alpine.min.js\"></script>
    
    {% block styles %}{% endblock %}
</head>
<body>
    <div class=\"hero-pattern-bg-wrapper\">
        {% include \x27partials/header.twig\x27 %}
    
        <main>
            {% block content %}{% endblock %}
        </main>

        <button x-show=\"showTopButton\" @click=\"window.scrollTo({top: 0, behavior: \x27smooth\x27})\" x-transition class=\"scroll-top-btn\">
            <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\" stroke-width=\"3\">
                <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M5 15l7-7 7 7\" />
            </svg>
        </button>

        {% include \x27partials/footer.twig\x27 %}

    </div>

    {% block scripts %}{% endblock %}
</body>
</html>", "layouts/main.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\layouts\\main.twig");
    }
}
