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

/* navigation/tree/quick_warp.twig */
class __TwigTemplate_b92b2fef369f16c23bbb68c9129402dd extends Template
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
        yield "<div class=\"pma_quick_warp\">
    ";
        // line 2
        if (($context["recent"] ?? null)) {
            yield ($context["recent"] ?? null);
        }
        // line 3
        yield "    ";
        if (($context["favorite"] ?? null)) {
            yield ($context["favorite"] ?? null);
        }
        // line 4
        yield "    <div class=\"clearfloat\"></div>
</div>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "navigation/tree/quick_warp.twig";
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
        return array (  50 => 4,  45 => 3,  41 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "navigation/tree/quick_warp.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/navigation/tree/quick_warp.twig");
    }
}
