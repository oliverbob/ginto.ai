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

/* table/page_with_secondary_tabs.twig */
class __TwigTemplate_140be48f4a96d8016d701e465376293d extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'content' => [$this, 'block_content'],
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        if (( !(null === CoreExtension::getAttribute($this->env, $this->source, ($context["relation_parameters"] ?? null), "relationFeature", [], "any", false, false, false, 1)) || ($context["is_foreign_key_supported"] ?? null))) {
            // line 2
            yield "  <ul class=\"nav nav-pills m-2 d-print-none\">
    <li class=\"nav-item\">
      <a href=\"";
            // line 4
            yield PhpMyAdmin\Url::getFromRoute("/table/structure", ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]);
            yield "\" id=\"table_structure_id\" class=\"nav-link";
            yield (((($context["route"] ?? null) == "/table/structure")) ? (" active") : (""));
            yield " disableAjax\">
        ";
            // line 5
            yield PhpMyAdmin\Html\Generator::getIcon("b_props", _gettext("Table structure"), true);
            yield "
      </a>
    </li>

    <li class=\"nav-item\">
      <a href=\"";
            // line 10
            yield PhpMyAdmin\Url::getFromRoute("/table/relation", ["db" => ($context["db"] ?? null), "table" => ($context["table"] ?? null)]);
            yield "\" id=\"table_relation_id\" class=\"nav-link";
            yield (((($context["route"] ?? null) == "/table/relation")) ? (" active") : (""));
            yield " disableAjax\">
        ";
            // line 11
            yield PhpMyAdmin\Html\Generator::getIcon("b_relations", _gettext("Relation view"), true);
            yield "
      </a>
    </li>
  </ul>
";
        }
        // line 16
        yield "
";
        // line 17
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable($this->env->getRuntime('PhpMyAdmin\FlashMessages')->getMessages());
        foreach ($context['_seq'] as $context["flash_key"] => $context["flash_messages"]) {
            // line 18
            yield "  ";
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable($context["flash_messages"]);
            foreach ($context['_seq'] as $context["_key"] => $context["flash_message"]) {
                // line 19
                yield "    <div class=\"alert alert-";
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["flash_key"], "html", null, true);
                yield "\" role=\"alert\">
      ";
                // line 20
                yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($context["flash_message"], "html", null, true);
                yield "
    </div>
  ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['flash_message'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['flash_key'], $context['flash_messages'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 24
        yield "
<div id=\"structure_content\">
  ";
        // line 26
        yield from $this->unwrap()->yieldBlock('content', $context, $blocks);
        // line 27
        yield "</div>
";
        return; yield '';
    }

    // line 26
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "table/page_with_secondary_tabs.twig";
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
        return array (  115 => 26,  109 => 27,  107 => 26,  103 => 24,  90 => 20,  85 => 19,  80 => 18,  76 => 17,  73 => 16,  65 => 11,  59 => 10,  51 => 5,  45 => 4,  41 => 2,  39 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "table/page_with_secondary_tabs.twig", "/home/oliverbob/ginto/vendor/phpmyadmin/phpmyadmin/templates/table/page_with_secondary_tabs.twig");
    }
}
