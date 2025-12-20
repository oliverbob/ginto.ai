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

/* display/results/table_navigation_button.twig */
class __TwigTemplate_815323d6853681d46cc8a1877d086f3f extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        yield "<td>
    <form action=\"";
        // line 2
        yield PhpMyAdmin\Url::getFromRoute("/sql");
        yield "\" method=\"post\" ";
        yield ($context["onsubmit"] ?? null);
        yield ">
        ";
        // line 3
        yield PhpMyAdmin\Url::getHiddenInputs(($context["db"] ?? null), ($context["table"] ?? null));
        yield "
        <input type=\"hidden\" name=\"sql_query\" value=\"";
        // line 4
        yield ($context["sql_query"] ?? null);
        yield "\">
        <input type=\"hidden\" name=\"pos\" value=\"";
        // line 5
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["pos"] ?? null), "html", null, true);
        yield "\">
        <input type=\"hidden\" name=\"is_browse_distinct\" value=\"";
        // line 6
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["is_browse_distinct"] ?? null), "html", null, true);
        yield "\">
        <input type=\"hidden\" name=\"goto\" value=\"";
        // line 7
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["goto"] ?? null), "html", null, true);
        yield "\">
        ";
        // line 8
        yield ($context["input_for_real_end"] ?? null);
        yield "
        <input type=\"submit\" name=\"navig\" class=\"btn btn-secondary ajax\" value=\"";
        // line 9
        yield ($context["caption_output"] ?? null);
        yield "\" title=\"";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["title"] ?? null), "html", null, true);
        yield "\">
    </form>
</td>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "display/results/table_navigation_button.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  71 => 9,  67 => 8,  63 => 7,  59 => 6,  55 => 5,  51 => 4,  47 => 3,  41 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "display/results/table_navigation_button.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/display/results/table_navigation_button.twig");
    }
}
