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

/* buyersguide.twig */
class __TwigTemplate_3352f09bde281a82aed79b51a25b8aae extends Template
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
        yield "    <div class=\"container hero-title-centered\">
        <h1>Buyer\x27s Guide</h1>
    </div>

    <section id=\"hero\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_bookshelf_edit.png\" alt=\"Bookshelf Illustration\">
            </div>
            <div class=\"split-text-side\">
                <p>When I bought my apartment, the real estate agent sent the offer-to-purchase to a bond originator to go to the banks and get me a bond loan for the property. All the banks declined.</p>
                <p>Naturally I thought it was my bad credit, and the fact that I worked on 100% commission. I called the originator anyway to find out what happened. Well, turns out that the originator had contracts with three of the four big banks, and the banks all declined due to reasons related to how they manage their balance sheet. It was never about me anyway.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_potplant_edit.png\" alt=\"Potplant Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <p>I called the agent and told her contractually, in terms of the offer-to-purchase, I still had time to get a bond and that she cannot send the property back to market. I took the contract to the fourth big bank and did the bond application myself. This app was successful and I was given the bond. Great!</p>
                <p>Anyways, the current owner had to pay for an electrical, plumbing and termite certificate that certified that the property is in good condition. This is a normal process when buying a place because the bank doesn’t want to give money for a place that is uninhabitable.</p>
                <p>A few weeks later I get a call from the bank’s conveyancing attorneys for an appointment for me to come sign the bond registration papers. They had me sign a stack of documents. Did I read it? Of course not. Nobody reads the contract anyways, right?</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_bathtub_edit.png\" alt=\"Bathtub Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>How property purchase transactions happen</h2>
                <p>One of the main things that happens in this whole property purchase transaction is that the new home owner only actually sees the monthly bond instalment when they are sitting across from the attorneys, at which point you’d feel sheepish to say to the attorney that you want to read it first. These guys also know how to put pressure on you to pen the contract, because they are usually in such a “hurry” that you can come away feeling like you’re wasting their time.</p>
                <p>Before you go see the attorneys, tell them to email you the contract ahead of time so that you can review it and have your questions ready for the in-person meeting. Don’t let anybody rush you through signing off on a 20-year commitment. Once it’s done, it’s difficult to undo. You are the customer, and these guys charge a lot of money for this one transaction. Get your money’s worth, but respect the people’s time.</p>
            </div>
        </div>
    </section>

    <section id=\"testimonials\" class=\"testimonials-alt\">
        <div class=\"container cta-block-alt\">
            <h2>Apply for your loan in minutes</h2>
            <div class=\"my-16\">
                <a href=\"/apply\" class=\"btn btn-primary\">Get Started</a>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_camera_edit.png\" alt=\"Camera Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <h2>Key questions to ask</h2>
                <p class=\"list-intro-text\">Some key questions to ask the current owner when viewing the property:</p>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">01</div>
                        <h3 class=\"feature-title\">Prepaid or a monthly bill?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Is electricity and water prepaid or does the account arrive end of the month?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">02</div>
                        <h3 class=\"feature-title\">How much are the utility bills?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">How much are they currently paying for water and electricity on a monthly basis?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">03</div>
                        <h3 class=\"feature-title\">Levies and agency fees?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">In a complex, how much are the levies and management agent charging monthly?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">04</div>
                        <h3 class=\"feature-title\">Municipal rates?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">How much are the rates with the municipality? Rates are basically a land tax charged to people who own property.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_carpet_edit.png\" alt=\"Carpet Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">05</div>
                        <h3 class=\"feature-title\">How much is the rent?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">If you are going to be renting out the property, ask the owner how much these properties rent for in the current market so you have an indication.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">06</div>
                        <h3 class=\"feature-title\">Is urgent maintenance needed?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">What, in the owner’s opinion, are the most urgent repairs that need to take place pretty soon.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">07</div>
                        <h3 class=\"feature-title\">What\x27s it like to live here?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">Ask some general questions about the neighbours and the neighbourhood.</p>
                    </div>
                </div>

                <p class=\"list-outro-text\">There are no dumb questions when making this kind of commitment. Make sure you write down your questions and that you populate the answers on your sheets. It’s easy to forget the detail. Now for a bonus tip!</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_clock_edit.png\" alt=\"Clock Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">08</div>
                        <h3 class=\"feature-title\">Hire your own professionals</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">If you are buying especially an older free-standing house, bring your own roof specialist, builder, electrician, plumber and pool guy to do an independent report. These people cost you money, of course, but their loyalty belongs to you and not to the seller and his estate agent.</p>
                    </div>
                </div>

                <p class=\"list-outro-text\">It is cheaper to burn some money at the outset and turn down the deal than it is to discover a nasty cracked wall or a leaking roof that compromises the entire structure. You use this information to negotiate a further discount on the property or you ask the current owner to fix these things as part of the transaction before you take ownership. Make sure what is agreed to is in the contract.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_couch_edit.png\" alt=\"Couch Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>Legal fees and charges</h2>
                <p>Then there are the conveyancing fees and transfer tax/duty. These are once off fees. Sometimes, if you’re a first-time home owner, the bank will give you a 108% bond. The 8% is to cover these costs. If you’re buying your second property, they expect you to pay for these things. Make sure you understand how these fees are handled so you are prepared for a cash payment, if required. These charges can be substantial.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_desk_edit.png\" alt=\"Desk Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>Insurance & assurance</h2>
                <p>Now the bank usually will require collateral from you for in the unlikely event that you kick the bucket. Sometimes they will provide it to you in the form of bond cover, other times they will request you to purchase a life cover policy which you will cede to the bank. Life cover can be purchased from a financial advisor who has a license with an insurance company.</p>
                <p>You see, banks are in the money lending game… they’re not in the property game. They want to know if you pass away, they are going to get their money back. That way your family also gets to keep the house.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split pb-12\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side pt-6\">
                <img src=\"/img/pic_door_edit.png\" alt=\"Door Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>How much house should you buy?</h2>
                <p>Totally up to you. Banks will limit how much they are willing to lend to you based on what they think the property is worth and how much they believe you can afford.</p>
                <p>My view is making sure that the bond instalment component is not more than a third of you and your spouse’s joint income. Remember, there will still be the other incidental charges attached to home ownership that will also need to be accounted for and put into your budget. Leave yourself some space to breathe.</p>
            </div>
        </div>
    </section>

    ";
        // line 209
        yield from $this->load("partials/cta.twig", 209)->unwrap()->yield($context);
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "buyersguide.twig";
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
        return array (  275 => 209,  70 => 6,  63 => 5,  52 => 3,  41 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("{% extends \x27layouts/main.twig\x27 %}

{% block title %}{{ title }}{% endblock %}

{% block content %}
    <div class=\"container hero-title-centered\">
        <h1>Buyer\x27s Guide</h1>
    </div>

    <section id=\"hero\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_bookshelf_edit.png\" alt=\"Bookshelf Illustration\">
            </div>
            <div class=\"split-text-side\">
                <p>When I bought my apartment, the real estate agent sent the offer-to-purchase to a bond originator to go to the banks and get me a bond loan for the property. All the banks declined.</p>
                <p>Naturally I thought it was my bad credit, and the fact that I worked on 100% commission. I called the originator anyway to find out what happened. Well, turns out that the originator had contracts with three of the four big banks, and the banks all declined due to reasons related to how they manage their balance sheet. It was never about me anyway.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_potplant_edit.png\" alt=\"Potplant Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <p>I called the agent and told her contractually, in terms of the offer-to-purchase, I still had time to get a bond and that she cannot send the property back to market. I took the contract to the fourth big bank and did the bond application myself. This app was successful and I was given the bond. Great!</p>
                <p>Anyways, the current owner had to pay for an electrical, plumbing and termite certificate that certified that the property is in good condition. This is a normal process when buying a place because the bank doesn’t want to give money for a place that is uninhabitable.</p>
                <p>A few weeks later I get a call from the bank’s conveyancing attorneys for an appointment for me to come sign the bond registration papers. They had me sign a stack of documents. Did I read it? Of course not. Nobody reads the contract anyways, right?</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_bathtub_edit.png\" alt=\"Bathtub Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>How property purchase transactions happen</h2>
                <p>One of the main things that happens in this whole property purchase transaction is that the new home owner only actually sees the monthly bond instalment when they are sitting across from the attorneys, at which point you’d feel sheepish to say to the attorney that you want to read it first. These guys also know how to put pressure on you to pen the contract, because they are usually in such a “hurry” that you can come away feeling like you’re wasting their time.</p>
                <p>Before you go see the attorneys, tell them to email you the contract ahead of time so that you can review it and have your questions ready for the in-person meeting. Don’t let anybody rush you through signing off on a 20-year commitment. Once it’s done, it’s difficult to undo. You are the customer, and these guys charge a lot of money for this one transaction. Get your money’s worth, but respect the people’s time.</p>
            </div>
        </div>
    </section>

    <section id=\"testimonials\" class=\"testimonials-alt\">
        <div class=\"container cta-block-alt\">
            <h2>Apply for your loan in minutes</h2>
            <div class=\"my-16\">
                <a href=\"/apply\" class=\"btn btn-primary\">Get Started</a>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_camera_edit.png\" alt=\"Camera Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <h2>Key questions to ask</h2>
                <p class=\"list-intro-text\">Some key questions to ask the current owner when viewing the property:</p>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">01</div>
                        <h3 class=\"feature-title\">Prepaid or a monthly bill?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">Is electricity and water prepaid or does the account arrive end of the month?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">02</div>
                        <h3 class=\"feature-title\">How much are the utility bills?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">How much are they currently paying for water and electricity on a monthly basis?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">03</div>
                        <h3 class=\"feature-title\">Levies and agency fees?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">In a complex, how much are the levies and management agent charging monthly?</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">04</div>
                        <h3 class=\"feature-title\">Municipal rates?</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">How much are the rates with the municipality? Rates are basically a land tax charged to people who own property.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_carpet_edit.png\" alt=\"Carpet Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">05</div>
                        <h3 class=\"feature-title\">How much is the rent?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">If you are going to be renting out the property, ask the owner how much these properties rent for in the current market so you have an indication.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">06</div>
                        <h3 class=\"feature-title\">Is urgent maintenance needed?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">What, in the owner’s opinion, are the most urgent repairs that need to take place pretty soon.</p>
                    </div>
                </div>

                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">07</div>
                        <h3 class=\"feature-title\">What\x27s it like to live here?</h3>
                    </div>
                    <div class=\"pr-desktop-20\">
                        <p class=\"feature-desc\">Ask some general questions about the neighbours and the neighbourhood.</p>
                    </div>
                </div>

                <p class=\"list-outro-text\">There are no dumb questions when making this kind of commitment. Make sure you write down your questions and that you populate the answers on your sheets. It’s easy to forget the detail. Now for a bonus tip!</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_clock_edit.png\" alt=\"Clock Illustration\">
            </div>
            <div class=\"features-list split-text-side list-container\">
                <div class=\"feature-item\">
                    <div class=\"feature-header\">
                        <div class=\"feature-badge\">08</div>
                        <h3 class=\"feature-title\">Hire your own professionals</h3>
                    </div>
                    <div>
                        <p class=\"feature-desc\">If you are buying especially an older free-standing house, bring your own roof specialist, builder, electrician, plumber and pool guy to do an independent report. These people cost you money, of course, but their loyalty belongs to you and not to the seller and his estate agent.</p>
                    </div>
                </div>

                <p class=\"list-outro-text\">It is cheaper to burn some money at the outset and turn down the deal than it is to discover a nasty cracked wall or a leaking roof that compromises the entire structure. You use this information to negotiate a further discount on the property or you ask the current owner to fix these things as part of the transaction before you take ownership. Make sure what is agreed to is in the contract.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_couch_edit.png\" alt=\"Couch Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>Legal fees and charges</h2>
                <p>Then there are the conveyancing fees and transfer tax/duty. These are once off fees. Sometimes, if you’re a first-time home owner, the bank will give you a 108% bond. The 8% is to cover these costs. If you’re buying your second property, they expect you to pay for these things. Make sure you understand how these fees are handled so you are prepared for a cash payment, if required. These charges can be substantial.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split\">
        <div class=\"container split-wrapper-reverse\">
            <div class=\"split-img-side\">
                <img src=\"/img/pic_desk_edit.png\" alt=\"Desk Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>Insurance & assurance</h2>
                <p>Now the bank usually will require collateral from you for in the unlikely event that you kick the bucket. Sometimes they will provide it to you in the form of bond cover, other times they will request you to purchase a life cover policy which you will cede to the bank. Life cover can be purchased from a financial advisor who has a license with an insurance company.</p>
                <p>You see, banks are in the money lending game… they’re not in the property game. They want to know if you pass away, they are going to get their money back. That way your family also gets to keep the house.</p>
            </div>
        </div>
    </section>

    <section id=\"features\" class=\"section-split pb-12\">
        <div class=\"container split-wrapper\">
            <div class=\"split-img-side pt-6\">
                <img src=\"/img/pic_door_edit.png\" alt=\"Door Illustration\">
            </div>
            <div class=\"split-text-side text-block-narrow\">
                <h2>How much house should you buy?</h2>
                <p>Totally up to you. Banks will limit how much they are willing to lend to you based on what they think the property is worth and how much they believe you can afford.</p>
                <p>My view is making sure that the bond instalment component is not more than a third of you and your spouse’s joint income. Remember, there will still be the other incidental charges attached to home ownership that will also need to be accounted for and put into your budget. Leave yourself some space to breathe.</p>
            </div>
        </div>
    </section>

    {% include \x27partials/cta.twig\x27 %}
{% endblock %}", "buyersguide.twig", "C:\\xampp\\htdocs\\slim-killer\\resources\\views\\buyersguide.twig");
    }
}
