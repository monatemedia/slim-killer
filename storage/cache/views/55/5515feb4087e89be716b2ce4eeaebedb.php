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

/* home.blade.php */
class __TwigTemplate_41d5c2de3c40d05a38746b21473e28e7 extends Template
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
        yield "@extends(\x27layouts.main\x27)

@section(\x27title\x27, \x27Manage Home — Apply for your loan in minutes\x27)

@section(\x27content\x27)
    <section id=\"hero\">
        <div class=\"container flex flex-col-reverse md:flex-row items-center px-6 mx-auto mt-10 space-y-0 md:space-y-0\">
            <div class=\"flex flex-col mb-32 space-y-12 md:w-1/2\">
                <h1 class=\"max-w-md text-4xl font-bold text-center md:text-5xl md:text-left pt-12 md:pt-0\">
                    Apply for your new home loan in minutes
                </h1>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    \"Manage contains all the information I\x27ve learnt over almost 15 years of buying, selling and managing property investments.\"
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Manage makes it simple for you to apply and get approved for your home or commercial loan now.\"
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    - Katniss Everdeen, Director at Manage
                </p>
                <div class=\"flex justify-center md:justify-start\">
                    <a href=\"/apply\" class=\"p-3 px-6 pt-2 text-white bg-red-600 rounded-full baseline hover:bg-red-500\">Get Started</a>
                </div>
            </div>
            <div class=\"md:w-1/2\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Profits Illustration\">
            </div>
        </div>
    </section>

    <section id=\"features\">
        <div class=\"container flex flex-col px-4 mx-auto mt-10 space-y-12 md:flex-row\">
            <div class=\"flex flex-col space-y-12 md:w-1/2\">
                <h2 class=\"max-w-md text-4xl font-bold text-center md:text-left\">
                    What\x27s different with Manage?
                </h2>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Manage helps you apply for your home or commercial property loan in minutes.
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Fill in your details and we will put you in touch with a top-notch bond originator who will get you the best bond deal for your needs.
                </p>
            </div>
            <div class=\"flex flex-col space-y-8 md:w-1/2\">
                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">01</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Bond approval within days</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Bond approval within days</h3>
                        <p class=\"text-sky-900\">
                            Let our originators do the hard work for you and they will contact you back with a bond offer. 
                        </p>
                    </div>
                </div>

                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">02</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Best rates for your loan</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Best rates for your loan</h3>
                        <p class=\"text-sky-900\">
                            When our originators negotiate your loan terms, you can rest assured that we will get you the best rate possible. We know what the banks are looking for so we can put your application in the best light to get you the most favourable interest rates. 
                        </p>
                    </div>
                </div>

                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">03</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Paid for by the bank</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Paid for by the bank</h3>
                        <p class=\"text-sky-900\">
                            Our originators are paid a commission on completion of a successful transaction by the bank. We shop your bond around for you to the major banks and they carry the costs of doing business. 
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"testimonials\" class=\"md:pb-32\">
        <div class=\"max-w-6xl px-5 mx-auto mt-32 mb-16 md:mb-0 text-center\">
            <h2 class=\"text-4xl font-bold text-center\">What Customers Say About Manage</h2>
            <div class=\"flex flex-col mt-24 md:flex-row md:space-x-6\">
                <div class=\"flex flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:w-1/3\">
                    <img src=\"/img/avatar-anisha.png\" alt=\"Anisha\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Anisha Jones</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Manage has made it easy to get our bond approved. Thank you Manage for your help!\" 
                    </p>
                </div>
                <div class=\"hidden flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:flex md:w-1/3\">
                    <img src=\"/img/avatar-ali.png\" alt=\"Ali\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Ali Watkins</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Dealing with manage has been only a pleasure. My bond got approved in days and the consultant was very helpful and professional.\" 
                    </p>
                </div>
                <div class=\"hidden flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:flex md:w-1/3\">
                    <img src=\"/img/avatar-richard.png\" alt=\"Richard\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Richard Able</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Manage helped our company buy the commercial building we were renting. The process was effortless and their consultant always kept us in the loop.\" 
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id=\"cta\" class=\"bg-red-600\">
        <div class=\"container flex flex-col items-center justify-between px-6 py-12 mx-auto space-y-12 md:flex-row md:space-y-0\">
            <h2 class=\"text-5xl font-bold leading-tight text-center text-white md:text-4xl md:max-w-xl md:text-left\">
                Apply for your loan in minutes
            </h2>
            <div>
                <a href=\"/apply\" class=\"p-3 px-6 pt-2 text-red-600 bg-white rounded-full shadow-2xl baseline hover:bg-slate-900 hover:text-white\">Get Started</a>
            </div>
        </div>
    </section>
@endsection";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "home.blade.php";
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
        return new Source("@extends(\x27layouts.main\x27)

@section(\x27title\x27, \x27Manage Home — Apply for your loan in minutes\x27)

@section(\x27content\x27)
    <section id=\"hero\">
        <div class=\"container flex flex-col-reverse md:flex-row items-center px-6 mx-auto mt-10 space-y-0 md:space-y-0\">
            <div class=\"flex flex-col mb-32 space-y-12 md:w-1/2\">
                <h1 class=\"max-w-md text-4xl font-bold text-center md:text-5xl md:text-left pt-12 md:pt-0\">
                    Apply for your new home loan in minutes
                </h1>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    \"Manage contains all the information I\x27ve learnt over almost 15 years of buying, selling and managing property investments.\"
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Manage makes it simple for you to apply and get approved for your home or commercial loan now.\"
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    - Katniss Everdeen, Director at Manage
                </p>
                <div class=\"flex justify-center md:justify-start\">
                    <a href=\"/apply\" class=\"p-3 px-6 pt-2 text-white bg-red-600 rounded-full baseline hover:bg-red-500\">Get Started</a>
                </div>
            </div>
            <div class=\"md:w-1/2\">
                <img src=\"/img/pic_profits_edit.png\" alt=\"Profits Illustration\">
            </div>
        </div>
    </section>

    <section id=\"features\">
        <div class=\"container flex flex-col px-4 mx-auto mt-10 space-y-12 md:flex-row\">
            <div class=\"flex flex-col space-y-12 md:w-1/2\">
                <h2 class=\"max-w-md text-4xl font-bold text-center md:text-left\">
                    What\x27s different with Manage?
                </h2>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Manage helps you apply for your home or commercial property loan in minutes.
                </p>
                <p class=\"max-w-sm text-center text-sky-900 md:text-left\">
                    Fill in your details and we will put you in touch with a top-notch bond originator who will get you the best bond deal for your needs.
                </p>
            </div>
            <div class=\"flex flex-col space-y-8 md:w-1/2\">
                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">01</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Bond approval within days</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Bond approval within days</h3>
                        <p class=\"text-sky-900\">
                            Let our originators do the hard work for you and they will contact you back with a bond offer. 
                        </p>
                    </div>
                </div>

                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">02</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Best rates for your loan</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Best rates for your loan</h3>
                        <p class=\"text-sky-900\">
                            When our originators negotiate your loan terms, you can rest assured that we will get you the best rate possible. We know what the banks are looking for so we can put your application in the best light to get you the most favourable interest rates. 
                        </p>
                    </div>
                </div>

                <div class=\"flex flex-col space-y-3 md:space-y-0 md:space-x-6 md:flex-row\">
                    <div class=\"rounded-l-full bg-red-400 md:bg-transparent\">
                        <div class=\"flex items-center space-x-2\">
                            <div class=\"px-4 py-2 text-white rounded-full md:py-1 bg-red-600\">03</div>
                            <h3 class=\"text-base font-bold md:mb-4 md:hidden\">Paid for by the bank</h3>
                        </div>
                    </div>
                    <div>
                        <h3 class=\"hidden mb-4 text-lg font-bold md:block\">Paid for by the bank</h3>
                        <p class=\"text-sky-900\">
                            Our originators are paid a commission on completion of a successful transaction by the bank. We shop your bond around for you to the major banks and they carry the costs of doing business. 
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"testimonials\" class=\"md:pb-32\">
        <div class=\"max-w-6xl px-5 mx-auto mt-32 mb-16 md:mb-0 text-center\">
            <h2 class=\"text-4xl font-bold text-center\">What Customers Say About Manage</h2>
            <div class=\"flex flex-col mt-24 md:flex-row md:space-x-6\">
                <div class=\"flex flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:w-1/3\">
                    <img src=\"/img/avatar-anisha.png\" alt=\"Anisha\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Anisha Jones</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Manage has made it easy to get our bond approved. Thank you Manage for your help!\" 
                    </p>
                </div>
                <div class=\"hidden flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:flex md:w-1/3\">
                    <img src=\"/img/avatar-ali.png\" alt=\"Ali\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Ali Watkins</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Dealing with manage has been only a pleasure. My bond got approved in days and the consultant was very helpful and professional.\" 
                    </p>
                </div>
                <div class=\"hidden flex-col items-center p-6 space-y-6 rounded-lg bg-slate-200 md:flex md:w-1/3\">
                    <img src=\"/img/avatar-richard.png\" alt=\"Richard\" class=\"w-16 -mt-14\">
                    <h5 class=\"text-lg font-bold\">Richard Able</h5>
                    <p class=\"text-sm text-sky-900\">
                        \"Manage helped our company buy the commercial building we were renting. The process was effortless and their consultant always kept us in the loop.\" 
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id=\"cta\" class=\"bg-red-600\">
        <div class=\"container flex flex-col items-center justify-between px-6 py-12 mx-auto space-y-12 md:flex-row md:space-y-0\">
            <h2 class=\"text-5xl font-bold leading-tight text-center text-white md:text-4xl md:max-w-xl md:text-left\">
                Apply for your loan in minutes
            </h2>
            <div>
                <a href=\"/apply\" class=\"p-3 px-6 pt-2 text-red-600 bg-white rounded-full shadow-2xl baseline hover:bg-slate-900 hover:text-white\">Get Started</a>
            </div>
        </div>
    </section>
@endsection", "home.blade.php", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\home.blade.php");
    }
}
