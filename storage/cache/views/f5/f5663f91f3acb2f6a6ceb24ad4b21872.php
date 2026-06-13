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

/* partials/cta.twig */
class __TwigTemplate_8efcfb84c5bdfcd9f26ac7677d0bd8a8 extends Template
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
        yield "<section id=\"cta\">
    <div class=\"container cta-wrapper\">
        <h2>Apply for your loan in minutes</h2>
        <div>
            <a href=\"/apply\" class=\"btn btn-white\">Get Started</a>
        </div>
    </div>
</section>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "partials/cta.twig";
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
        return new Source("<section id=\"cta\">
    <div class=\"container cta-wrapper\">
        <h2>Apply for your loan in minutes</h2>
        <div>
            <a href=\"/apply\" class=\"btn btn-white\">Get Started</a>
        </div>
    </div>
</section>", "partials/cta.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\partials\\cta.twig");
    }
}
