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

/* partials/footer.twig */
class __TwigTemplate_3e5e608228aa54a25b051292d2585f1f extends Template
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
        yield "<footer>
    <div class=\"container footer-wrapper\">
        <div class=\"footer-brand\">
            <div class=\"copyright mobile-copyright\">
                Copyright &copy; ";
        // line 5
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate("now", "Y"), "html", null, true);
        yield ", All Rights Reserved
            </div>
            <div>
                <a href=\"/\">
                    <img src=\"/img/logo-white.svg\" class=\"footer-logo-img\" alt=\"Manage Logo\">
                </a>
            </div>
            <div class=\"footer-socials\">
                <a href=\"#\"><img src=\"/img/icon-facebook.svg\" alt=\"Facebook\"></a>
                <a href=\"#\"><img src=\"/img/icon-youtube.svg\" alt=\"YouTube\"></a>
                <a href=\"#\"><img src=\"/img/icon-twitter.svg\" alt=\"Twitter\"></a>
                <a href=\"#\"><img src=\"/img/icon-pinterest.svg\" alt=\"Pinterest\"></a>
                <a href=\"#\"><img src=\"/img/icon-instagram.svg\" alt=\"Instagram\"></a>
            </div>
        </div>

        <div class=\"footer-links-grid\">
            <div class=\"footer-col\">
                <a href=\"/\">Home</a>
                <a href=\"/calculator\">Calculator</a>
                <a href=\"/buyers-guide\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\">Property Secrets</a>
            </div>
            <div class=\"footer-col\">
                <a href=\"https://github.com/monatemedia/bond-originator\" target=\"_blank\">Github</a>
                <a href=\"https://www.monatemedia.com/\" target=\"_blank\">Developer</a>
                <a href=\"https://www.linkedin.com/in/edwardbaitsewe/\" target=\"_blank\">LinkedIn</a>
            </div>
        </div>

        <div class=\"footer-form-side\">
            <form name=\"subscriberForm\" id=\"subscriberForm\" method=\"POST\" action=\"/subscribe\">
                <div class=\"form-inline\">
                    <input type=\"email\" name=\"email\" id=\"subscriber\" placeholder=\"Updates in your inbox\" required />
                    <button class=\"btn btn-primary\" type=\"submit\">Go</button>
                </div>
            </form>
            <div class=\"copyright desktop-copyright\">
                Copyright &copy; ";
        // line 43
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->extensions['Twig\Extension\CoreExtension']->formatDate("now", "Y"), "html", null, true);
        yield ", All Rights Reserved
            </div>
        </div>
    </div>
</footer>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "partials/footer.twig";
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
        return array (  89 => 43,  48 => 5,  42 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("<footer>
    <div class=\"container footer-wrapper\">
        <div class=\"footer-brand\">
            <div class=\"copyright mobile-copyright\">
                Copyright &copy; {{ \"now\"|date(\"Y\") }}, All Rights Reserved
            </div>
            <div>
                <a href=\"/\">
                    <img src=\"/img/logo-white.svg\" class=\"footer-logo-img\" alt=\"Manage Logo\">
                </a>
            </div>
            <div class=\"footer-socials\">
                <a href=\"#\"><img src=\"/img/icon-facebook.svg\" alt=\"Facebook\"></a>
                <a href=\"#\"><img src=\"/img/icon-youtube.svg\" alt=\"YouTube\"></a>
                <a href=\"#\"><img src=\"/img/icon-twitter.svg\" alt=\"Twitter\"></a>
                <a href=\"#\"><img src=\"/img/icon-pinterest.svg\" alt=\"Pinterest\"></a>
                <a href=\"#\"><img src=\"/img/icon-instagram.svg\" alt=\"Instagram\"></a>
            </div>
        </div>

        <div class=\"footer-links-grid\">
            <div class=\"footer-col\">
                <a href=\"/\">Home</a>
                <a href=\"/calculator\">Calculator</a>
                <a href=\"/buyers-guide\">Buyer\x27s Guide</a>
                <a href=\"/property-secrets\">Property Secrets</a>
            </div>
            <div class=\"footer-col\">
                <a href=\"https://github.com/monatemedia/bond-originator\" target=\"_blank\">Github</a>
                <a href=\"https://www.monatemedia.com/\" target=\"_blank\">Developer</a>
                <a href=\"https://www.linkedin.com/in/edwardbaitsewe/\" target=\"_blank\">LinkedIn</a>
            </div>
        </div>

        <div class=\"footer-form-side\">
            <form name=\"subscriberForm\" id=\"subscriberForm\" method=\"POST\" action=\"/subscribe\">
                <div class=\"form-inline\">
                    <input type=\"email\" name=\"email\" id=\"subscriber\" placeholder=\"Updates in your inbox\" required />
                    <button class=\"btn btn-primary\" type=\"submit\">Go</button>
                </div>
            </form>
            <div class=\"copyright desktop-copyright\">
                Copyright &copy; {{ \"now\"|date(\"Y\") }}, All Rights Reserved
            </div>
        </div>
    </div>
</footer>", "partials/footer.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\partials\\footer.twig");
    }
}
