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

/* apply.twig */
class __TwigTemplate_be39442b2110579a7e3bc1905ae0c4c2 extends Template
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
        yield "Bond Application | Manage";
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
        yield "<section id=\"hero\">
    <div class=\"container hero-wrapper\">
        
        <div class=\"hero-left\">
            <h1>Bond Application</h1>
            <p>Complete the form below and one of our agents will be in touch shortly.</p>
        </div>
        
        <div style=\"width: 100%; max-width: 500px;\">
            <img src=\"/img/pic_candle_chart-edit.png\" alt=\"Application\">
        </div>

    </div>
</section>

<section id=\"form\" class=\"section-split\">
    <div class=\"container split-wrapper\">
        
        <div class=\"split-img-side\">
            <img src=\"/img/pic_piechart_edit.png\" alt=\"Process\" style=\"width: 100%; max-width: 500px; display: block; margin: 0 auto;\">
        </div>
        
        <div class=\"split-text-side\">
            <form action=\"/apply\" method=\"POST\" class=\"form-card\"
                  x-data=\"applyFormValidation()\"
                  @submit.prevent=\"submitForm(\$el)\">
                
                <div class=\"form-row-split\">
                    <div class=\"form-group flex-1\">
                        <label class=\"form-label\">First Name*</label>
                        <input class=\"form-control\" 
                               :class=\"errors.first_name ? \x27form-control-error\x27 : \x27\x27\"
                               name=\"first_name\" 
                               type=\"text\" 
                               x-model=\"fields.first_name\" 
                               @blur=\"validateField(\x27first_name\x27)\">
                        <p class=\"error-text\" x-show=\"errors.first_name\" x-text=\"errors.first_name\"></p>
                    </div>
                    
                    <div class=\"form-group flex-1\">
                        <label class=\"form-label\">Last Name*</label>
                        <input class=\"form-control\" 
                               :class=\"errors.last_name ? \x27form-control-error\x27 : \x27\x27\"
                               name=\"last_name\" 
                               type=\"text\" 
                               x-model=\"fields.last_name\" 
                               @blur=\"validateField(\x27last_name\x27)\">
                        <p class=\"error-text\" x-show=\"errors.last_name\" x-text=\"errors.last_name\"></p>
                    </div>
                </div>
                
                <div class=\"form-group\">
                    <label class=\"form-label\">Phone Number*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.phone ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"phone\" 
                           type=\"tel\" 
                           placeholder=\"012 345 6789\" 
                           maxlength=\"10\"
                           x-model=\"fields.phone\" 
                           @blur=\"validateField(\x27phone\x27)\">
                    <p class=\"error-text\" x-show=\"errors.phone\" x-text=\"errors.phone\"></p>
                </div>

                <div class=\"form-group\">
                    <label class=\"form-label\">Email Address*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.email ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"email\" 
                           type=\"email\" 
                           x-model=\"fields.email\" 
                           @blur=\"validateField(\x27email\x27)\">
                    <p class=\"error-text\" x-show=\"errors.email\" x-text=\"errors.email\"></p>
                </div>

                <div class=\"form-group\">
                    <label class=\"form-label\">Bond Amount*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.bond_amount ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"bond_amount\" 
                           type=\"text\" 
                           x-model=\"fields.bond_amount\" 
                           @blur=\"validateField(\x27bond_amount\x27)\">
                    <p class=\"error-text\" x-show=\"errors.bond_amount\" x-text=\"errors.bond_amount\"></p>
                </div>

                <div style=\"margin-top: 24px;\">
                    <button class=\"btn btn-primary\" type=\"submit\">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function applyFormValidation() {
    return {
        fields: { first_name: \x27\x27, last_name: \x27\x27, phone: \x27\x27, email: \x27\x27, bond_amount: \x27\x27 },
        errors: { first_name: \x27\x27, last_name: \x27\x27, phone: \x27\x27, email: \x27\x27, bond_amount: \x27\x27 },

        validateField(name) {
            let value = this.fields[name].trim();
            this.errors[name] = \x27\x27;

            if (!value) {
                if (name === \x27first_name\x27) this.errors[name] = \"Please enter your first name\";
                if (name === \x27last_name\x27) this.errors[name] = \"Please enter your last name\";
                if (name === \x27phone\x27) this.errors[name] = \"Please enter your phone number\";
                if (name === \x27email\x27) this.errors[name] = \"Please enter your email\";
                if (name === \x27bond_amount\x27) this.errors[name] = \"Please enter your bond amount\";
                return false;
            }

            if ((name === \x27first_name\x27 || name === \x27last_name\x27) && value.length < 2) {
                this.errors[name] = \"Name at least 2 characters\";
            }

            if (name === \x27phone\x27) {
                if (!/^\\d+\$/.test(value)) {
                    this.errors[name] = \"Enter numbers only please\";
                } else if (value.length !== 10) {
                    this.errors[name] = \"Please enter a 10 digit number\";
                }
            }

            if (name === \x27email\x27 && !/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+\$/.test(value)) {
                this.errors[name] = \"Not a valid email address\";
            }

            if (name === \x27bond_amount\x27) {
                if (!/^\\d+\$/.test(value)) {
                    this.errors[name] = \"Please enter numbers only\";
                } else if (value.length < 6) {
                    this.errors[name] = \"Please enter at least six figures\";
                }
            }

            return this.errors[name] === \x27\x27;
        },

        submitForm(formElement) {
            let isValid = true;
            Object.keys(this.fields).forEach(key => {
                if (!this.validateField(key)) { isValid = false; }
            });
            if (isValid) { formElement.submit(); }
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
        return "apply.twig";
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

{% block title %}Bond Application | Manage{% endblock %}

{% block content %}
<section id=\"hero\">
    <div class=\"container hero-wrapper\">
        
        <div class=\"hero-left\">
            <h1>Bond Application</h1>
            <p>Complete the form below and one of our agents will be in touch shortly.</p>
        </div>
        
        <div style=\"width: 100%; max-width: 500px;\">
            <img src=\"/img/pic_candle_chart-edit.png\" alt=\"Application\">
        </div>

    </div>
</section>

<section id=\"form\" class=\"section-split\">
    <div class=\"container split-wrapper\">
        
        <div class=\"split-img-side\">
            <img src=\"/img/pic_piechart_edit.png\" alt=\"Process\" style=\"width: 100%; max-width: 500px; display: block; margin: 0 auto;\">
        </div>
        
        <div class=\"split-text-side\">
            <form action=\"/apply\" method=\"POST\" class=\"form-card\"
                  x-data=\"applyFormValidation()\"
                  @submit.prevent=\"submitForm(\$el)\">
                
                <div class=\"form-row-split\">
                    <div class=\"form-group flex-1\">
                        <label class=\"form-label\">First Name*</label>
                        <input class=\"form-control\" 
                               :class=\"errors.first_name ? \x27form-control-error\x27 : \x27\x27\"
                               name=\"first_name\" 
                               type=\"text\" 
                               x-model=\"fields.first_name\" 
                               @blur=\"validateField(\x27first_name\x27)\">
                        <p class=\"error-text\" x-show=\"errors.first_name\" x-text=\"errors.first_name\"></p>
                    </div>
                    
                    <div class=\"form-group flex-1\">
                        <label class=\"form-label\">Last Name*</label>
                        <input class=\"form-control\" 
                               :class=\"errors.last_name ? \x27form-control-error\x27 : \x27\x27\"
                               name=\"last_name\" 
                               type=\"text\" 
                               x-model=\"fields.last_name\" 
                               @blur=\"validateField(\x27last_name\x27)\">
                        <p class=\"error-text\" x-show=\"errors.last_name\" x-text=\"errors.last_name\"></p>
                    </div>
                </div>
                
                <div class=\"form-group\">
                    <label class=\"form-label\">Phone Number*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.phone ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"phone\" 
                           type=\"tel\" 
                           placeholder=\"012 345 6789\" 
                           maxlength=\"10\"
                           x-model=\"fields.phone\" 
                           @blur=\"validateField(\x27phone\x27)\">
                    <p class=\"error-text\" x-show=\"errors.phone\" x-text=\"errors.phone\"></p>
                </div>

                <div class=\"form-group\">
                    <label class=\"form-label\">Email Address*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.email ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"email\" 
                           type=\"email\" 
                           x-model=\"fields.email\" 
                           @blur=\"validateField(\x27email\x27)\">
                    <p class=\"error-text\" x-show=\"errors.email\" x-text=\"errors.email\"></p>
                </div>

                <div class=\"form-group\">
                    <label class=\"form-label\">Bond Amount*</label>
                    <input class=\"form-control\" 
                           :class=\"errors.bond_amount ? \x27form-control-error\x27 : \x27\x27\"
                           name=\"bond_amount\" 
                           type=\"text\" 
                           x-model=\"fields.bond_amount\" 
                           @blur=\"validateField(\x27bond_amount\x27)\">
                    <p class=\"error-text\" x-show=\"errors.bond_amount\" x-text=\"errors.bond_amount\"></p>
                </div>

                <div style=\"margin-top: 24px;\">
                    <button class=\"btn btn-primary\" type=\"submit\">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function applyFormValidation() {
    return {
        fields: { first_name: \x27\x27, last_name: \x27\x27, phone: \x27\x27, email: \x27\x27, bond_amount: \x27\x27 },
        errors: { first_name: \x27\x27, last_name: \x27\x27, phone: \x27\x27, email: \x27\x27, bond_amount: \x27\x27 },

        validateField(name) {
            let value = this.fields[name].trim();
            this.errors[name] = \x27\x27;

            if (!value) {
                if (name === \x27first_name\x27) this.errors[name] = \"Please enter your first name\";
                if (name === \x27last_name\x27) this.errors[name] = \"Please enter your last name\";
                if (name === \x27phone\x27) this.errors[name] = \"Please enter your phone number\";
                if (name === \x27email\x27) this.errors[name] = \"Please enter your email\";
                if (name === \x27bond_amount\x27) this.errors[name] = \"Please enter your bond amount\";
                return false;
            }

            if ((name === \x27first_name\x27 || name === \x27last_name\x27) && value.length < 2) {
                this.errors[name] = \"Name at least 2 characters\";
            }

            if (name === \x27phone\x27) {
                if (!/^\\d+\$/.test(value)) {
                    this.errors[name] = \"Enter numbers only please\";
                } else if (value.length !== 10) {
                    this.errors[name] = \"Please enter a 10 digit number\";
                }
            }

            if (name === \x27email\x27 && !/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+\$/.test(value)) {
                this.errors[name] = \"Not a valid email address\";
            }

            if (name === \x27bond_amount\x27) {
                if (!/^\\d+\$/.test(value)) {
                    this.errors[name] = \"Please enter numbers only\";
                } else if (value.length < 6) {
                    this.errors[name] = \"Please enter at least six figures\";
                }
            }

            return this.errors[name] === \x27\x27;
        },

        submitForm(formElement) {
            let isValid = true;
            Object.keys(this.fields).forEach(key => {
                if (!this.validateField(key)) { isValid = false; }
            });
            if (isValid) { formElement.submit(); }
        }
    }
}
</script>
{% endblock %}", "apply.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\apply.twig");
    }
}
