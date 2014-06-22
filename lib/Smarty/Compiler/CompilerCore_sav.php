<?php
namespace Smarty\Compiler;

use Smarty\Compiler;
use Smarty\Template\Context;
use Smarty\Template;

/**
 * Smarty Internal Plugin Smarty Template Compiler Base
 * This file contains the basic classes and methods for compiling Smarty templates with lexer/parser
 *
 * @package Smarty\Compiler\PHP
 * @author  Uwe Tews
 */

/**
 * Main abstract compiler class
 *
 * @package Smarty\Compiler\PHP
 */
class CompilerCore extends Compiler
{

    /**
     * compile tag objects
     *
     * @var array
     */
    public static $_tag_objects = array();
    /**
     * cache of parser class name by source language
     *
     * @var array
     */
    static $parser_class_cache = array();
    /**
     * cache of lexer class name by source language
     *
     * @var array
     */
    static $lexer_class_cache = array();
    /**
     * Context object
     *
     * @var Context
     */
    public $context = null;

    /**
     * line offset to start of template source
     *
     * @var int
     */
    public $line_offset = 0;
    /**
     * flag for strip mode
     *
     * @var bool
     */
    public $strip = false;
    /**
     * flag for nocache code not setting $has_nocache_flag
     *
     * @var bool
     */
    public $nocache_nolog = false;
    /**
     * suppress generation of nocache code
     *
     * @var bool
     */
    public $suppressNocacheProcessing = false;
    /**
     * flag when compiling inheritance
     *
     * @var bool
     */
    public $isInheritance = false;
    /**
     * flag when compiling inheritance
     *
     * @var bool
     */
    public $isInheritanceChild = false;
    /**
     * force compilation of complete template as nocache
     * 0 = off
     * 1 = observe nocache flags on template type recompiled
     * 2 = force all code to be nocache
     *
     * @var integer
     */
    public $forceNocache = 0;
    /**
     * tag stack
     *
     * @var array
     */
    public $_tag_stack = array();
    /**
     * file dependencies
     *
     * @var array
     */
    public $file_dependency = array();
    /**
     * block function properties
     *
     * @var array
     */
    public $inheritance_blocks = array();
    /**
     * block function compiled code
     *
     * @var array
     */
    public $inheritance_blocks_code = array();
    /**
     * block name index
     *
     * @var integer
     */
    public $block_name_index = 0;
    /**
     * inheritance block nesting level
     *
     * @var integer
     */
    public $block_nesting_level = 0;
    /**
     * block nesting info
     *
     * @var array
     */
    public $block_nesting_info = array();
    /**
     * compiled footer code
     *
     * @var array
     */
    public $compiled_footer_code = null;
    /**
     * /**
     * plugins loaded by default plugin handler
     *
     * @var array
     */
    public $default_handler_plugins = array();
    /**
     * saved preprocessed modifier list
     *
     * @var mixed
     */
    public $default_modifier_list = null;
    /**
     * suppress template property header code in compiled template
     *
     * @var bool
     */
    public $suppressTemplatePropertyHeader = false;
    /**
     * suppress processing of post filter
     *
     * @var bool
     */
    public $suppressPostFilter = false;
    /**
     * flag if compiled template file shall we written
     *
     * @var bool
     */
    public $write_compiled_code = true;
    /**
     * flag if template does contain nocache code sections
     *
     * @var boolean
     */
    public $has_nocache_code = false;
    /**
     * flag if currently a template function is compiled
     *
     * @var bool
     */
    public $compiles_template_function = false;
    /**
     * called subfuntions from template function
     *
     * @var array
     */
    public $called_template_functions = array();
    /**
     * template functions called nocache
     *
     * @var array
     */
    public $called_nocache_template_functions = array();
    /**
     * required plugins
     *
     * @var array
     * @internal
     */
    public $required_plugins = array('compiled' => array(), 'nocache' => array());
    /**
     * flags for used modifier plugins
     *
     * @var array
     */
    public $modifier_plugins = array();
    /**
     * type of already compiled modifier
     *
     * @var array
     */
    public $known_modifier_type = array();
    /**
     * Compiled Node
     * This is the node which holds the content which will be written to the compiled resource
     *
     * @var \Smarty_Compiler_Php_Node_Compiled
     */
    public $compiled_node = null;
    /**
     * Compiled filepath
     */
    public $compiled_filepath = '';
    /**
     * local variable name in compiled code for template output
     *
     * @var null|string
     */
    public $output_var = 'output';
    /**
     * Timestamp when we started compilation
     *
     * @var int
     */
    public $timestamp = 0;
    public $tag_cache = array();
    public $template_laguage_cache = array();
    public $code_laguage_cache = array();
    public $has_code = false;

    // TODO check this solution
    public $has_output = false;
    /**
     * Stack for output_var
     *
     * @var array
     */
    private $output_var_stack = array();

    /**
     * Initialize compiler

     */
    public function __construct()
    {
        // make sure that we don't run into backtrack limit errors
        ini_set('pcre.backtrack_limit', - 1);
    }

    /**
     * Check if class exists
     *
     * @param string $class     class name
     * @param bool   $exception flag if exception shall be thrown (default true)
     *
     * @return bool return status
     * @throws \Smarty_Compiler_Exception_MissingClass
     */
    public function classExists($class, $exception = true)
    {
        if (class_exists($class)) {
            return true;
        }
        if ($exception) {
            throw new \Smarty_Compiler_Exception_MissingClass($class);
        }
        return false;
    }

    /**
     * lazy loads internal compile plugin for tag and calls the compile method
     * compile objects cached for reuse.
     * class name format:  \Smarty_Compiler_Php_NodeCompiler_Tag_TagName
     *
     * @param  string $tag    tag name
     * @param  array  $args   list of tag attributes
     * @param  mixed  $param1 optional parameter
     * @param  mixed  $param2 optional parameter
     * @param  mixed  $param3 optional parameter
     *
     * @return string compiled code
     */
    public function compileCoreTag($tag, $args, $param1 = null, $param2 = null, $param3 = null)
    {
        // re-use object if already exists
        if (isset(self::$_tag_objects[$tag])) {
            // compile this tag
            return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
        }
        // check if tag allowed by security
        if (!isset($this->context->smarty->securityPolicy) || $this->context->smarty->securityPolicy->isTrustedTag($tag, $this)) {
            $class = '\Smarty_Compiler_Php_NodeCompiler_Tag_' . $tag;
            if (!class_exists($class, true)) {
                if (substr($tag, - 5) == 'close') {
                    $base_class = substr($tag, 0, - 5);
                    if (!class_exists($base_class, true)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
            self::$_tag_objects[$tag] = new $class;
            // compile this tag
            return self::$_tag_objects[$tag]->compile($args, $this, $param1, $param2, $param3);
        }
        // no internal compile plugin for this tag
        return false;
    }

    /**
     * Compile node
     *
     * @param \Smarty_Compiler_Node $node
     * @param Context               $context
     * @param null                  $indentation
     *
     * @throws Exception
     * @return string compiled code
     */
    public function compileNode(\Smarty_Compiler_Node $node, Context $context, $indentation = null)
    {
        try {
            // call compiler
            $node->compileNode($node, false);
            $code = $node->getFormated($indentation);
        }
        catch (Exception $e) {
            // in case of exception free memory
            $parser = $node->parser;
            $parser->cleanup();
            unset($parser);
            $node->cleanup();
            unset($node);
            throw $e;
        }

        $parser = $node->parser;
        $parser->cleanup();
        unset($parser);
        $node->cleanup();
        unset($node);

        return $code;
    }

    /**
     * Compile node and its optional subtree nodes and move precompiled code into current node
     *
     * @param null|Node $node   optional target node for compiled code
     * @param bool      $delete flag if compiled nodes shall be deleted
     *
     * @return Node  current node
     */
    public function compileNodexx($node, $delete = true)
    {
        // try external compiler class for this node
        if (false !== $callback = $this->compiler->getNodeCompilerCallback($node)) {
            $callback($this, $node, $delete);
            return $this;
        }
        // compiled code in node so just copy it
        if (isset($node->code)) {
            $this->raw($node->code);
            return $this;
        }
        // try compile method in node object
        if (method_exists($node, 'compile')) {
            $node->compile($this, $delete);
        }
        // fall through compile it's subtree
        if (!empty($node->subtreeNodes)) {
            $this->compileNodeArray($node->subtreeNodes, $delete);
        }
        return $this;
    }

    /**
     * Compiles from resource
     *
     * @param Context               $context
     * @param \Smarty_Compiler_Node $node
     * @param bool                  $pre_filter  flag for prefilter
     * @param bool                  $post_filter flag for postfilter
     *
     * @return string compiled code
     */
    public function compileResource(Context $context, $node = null, $pre_filter = true, $post_filter = true)
    {
        if ($context->smarty->debugging) {
            \Smarty_Debug::start_compile($context);
        }

        //        $root_node = isset($node) ? $node : $this->instanceRootNode($context);
        $root_node = null;
        $context->smarty->_current_file = $saved_filepath = $context->filepath;
        $code = $this->compileSource($context, $context->getContent(), $root_node, $pre_filter, $post_filter);
        $context->filepath = $saved_filepath;

        unset($root_node);

        if ($context->smarty->debugging) {
            \Smarty_Debug::end_compile($context);
        }
        return $code;
    }

    /**
     * Compiles from source string
     *
     * @param Context               $context
     * @param string                $source
     * @param \Smarty_Compiler_Node $node
     * @param bool                  $pre_filter  flag for prefilter
     * @param bool                  $post_filter flag for postfilter
     *
     * @throws Exception
     * @return string compiled code
     */
    public function compileSource(Context $context, $source, $node, $pre_filter = false, $post_filter = false)
    {
        // run prefilter if required
        if ($pre_filter && isset($context->smarty->_autoloadFilters['pre']) || isset($context->smarty->_registered['filter']['pre'])) {
            $source = $context->smarty->runFilter('pre', $source, $this);
        }
        try {
            // get root node
            $nodeClass = $this->getDefaultNodeName($context);
            // instance parser and load source
            $parser = $this->instanceParser($context)
                ->setSource($source);
            unset($source);
            $node = $this->instanceNode($nodeClass, $parser);
            $node->parse();
            $node->compile();
            $code = $node->compiled;
            $node->cleanup();
            unset($node);
            echo '<br>exit<br>';
        }
        catch (Exception $e) {
            // in case of exception free memory
            //           $parser = $node->parser;
            //           $parser->cleanup();
            //           unset($parser);
            //           $node->cleanup();
            //           unset($node);
            throw $e;
        }

        // run postfilter if required on compiled template code
        if ($post_filter && (isset($context->smarty->_autoloadFilters['post']) || isset($context->smarty->_registered['filter']['post']))) {
            $code = $context->smarty->runFilter('post', $code, $context->smarty, $this);
        }
        return $code;
    }

    /**
     * Compile Tag
     * This is a call back from the lexer/parser
     * It executes the required compile plugin for the Smarty tag
     *
     * @param  string $tag       tag name
     * @param  array  $args      array with tag attributes
     * @param  array  $parameter array with compilation parameter
     *
     * @return string compiled code
     */
    public function compilexTag($tag, $args, $parameter = array())
    {
        // $args contains the attributes parsed and compiled by the lexer/parser
        // assume that tag does compile into code, but creates no HTML output
        $this->has_code = true;
        $this->has_output = false;
        // log tag/attributes
        //TODO mit trace back
        if (isset($this->context->smarty->get_used_tags) && $this->context->smarty->get_used_tags) {
            $this->context->smarty->used_tags[] = array($tag, $args);
        }
        // check nocache option flag
        if (in_array("'nocache'", $args) || in_array(array('nocache' => 'true'), $args)
            || in_array(array('nocache' => '"true"'), $args) || in_array(array('nocache' => "'true'"), $args)
        ) {
            $this->tag_nocache = true;
        }
        // tags with _ like load_config need processing
        if (strpos($tag, '_') === false || strpos($tag, 'Internal_') === 0) {
            $_tag = $tag;
        } else {
            $_tag = '';
            $parts = explode('_', $tag);
            foreach ($parts as $part) {
                $_tag .= ucfirst($part);
            }
        }
        // compile the smarty tag (required compile classes to compile the tag are autoloaded)
        if (($_output = $this->compileCoreTag($_tag, $args, $parameter)) === false) {
            if (isset($this->_templateFunctions[$tag])) {
                // template defined by {template} tag
                $args['_attr']['name'] = "'" . $tag . "'";
                $_output = $this->compileCoreTag('Call', $args, $parameter);
            }
        }
        if ($_output !== false) {
            if ($_output !== true) {
                // did we get compiled code
                if ($this->has_code) {
                    // return compiled code
                    return $_output;
                }
            }
            // tag did not produce compiled code
            return '';
        } else {
            // map_named attributes
            if (isset($args['_attr'])) {
                foreach ($args['_attr'] as $attribute) {
                    if (is_array($attribute)) {
                        $args = array_merge($args, $attribute);
                    }
                }
            }
            // not an internal compiler tag
            if (strlen($tag) < 6 || substr($tag, - 5) != 'close') {
                // check if tag is a registered object
                if (isset($this->context->smarty->_registered['object'][$tag]) && isset($parameter['object_method'])) {
                    $method = $parameter['object_method'];
                    if (!in_array($method, $this->context->smarty->_registered['object'][$tag][3]) &&
                        (empty($this->context->smarty->_registered['object'][$tag][1]) || in_array($method, $this->context->smarty->_registered['object'][$tag][1]))
                    ) {
                        return $this->compileCoreTag('Internal_ObjectFunction', $args, $parameter, $tag, $method);
                    } elseif (in_array($method, $this->context->smarty->_registered['object'][$tag][3])) {
                        return $this->compileCoreTag('Internal_ObjectBlockFunction', $args, $parameter, $tag, $method);
                    } else {
                        $this->error('unallowed method "' . $method . '" in registered object "' . $tag . '"', $this->lex->taglineno);
                    }
                }
                // check if tag is registered
                foreach (array(Smarty::PLUGIN_COMPILER, Smarty::PLUGIN_FUNCTION, Smarty::PLUGIN_BLOCK) as $plugin_type) {
                    if (isset($this->context->smarty->_registered['plugin'][$plugin_type][$tag])) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        }
                        // compile registered function or block function
                        if ($plugin_type == Smarty::PLUGIN_FUNCTION || $plugin_type == Smarty::PLUGIN_BLOCK) {
                            return $this->compileCoreTag('Internal_Registered' . ucfirst($plugin_type), $args, $parameter, $tag);
                        }
                    }
                }
                // check plugins from plugins folder
                foreach (\Smarty_Compiler::$plugin_search_order as $plugin_type) {
                    if ($plugin_type == Smarty::PLUGIN_COMPILER && $this->context->smarty->_loadPlugin('\Smarty_compiler_' . $tag) && (!isset($this->context->smarty->securityPolicy) || $this->context->smarty->securityPolicy->isTrustedTag($tag, $this))) {
                        $plugin = 'smarty_compiler_' . $tag;
                        if (is_callable($plugin) || class_exists($plugin, false)) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        }
                        $this->error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
                    } else {
                        if ($function = $this->getPlugin($tag, $plugin_type)) {
                            if (!isset($this->context->smarty->securityPolicy) || $this->context->smarty->securityPolicy->isTrustedTag($tag, $this)) {
                                return $this->compileCoreTag('Internal_Plugin' . ucfirst($plugin_type), $args, $parameter, $tag, $function);
                            }
                        }
                    }
                }
                if (is_callable($this->context->smarty->default_plugin_handler_func)) {
                    $found = false;
                    // look for already resolved tags
                    foreach (\Smarty_Compiler::$plugin_search_order as $plugin_type) {
                        if (isset($this->default_handler_plugins[$plugin_type][$tag])) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        // call default handler
                        foreach (\Smarty_Compiler::$plugin_search_order as $plugin_type) {
                            if ($this->getPluginFromDefaultHandler($tag, $plugin_type)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                    if ($found) {
                        // if compiler function plugin call it now
                        if ($plugin_type == Smarty::PLUGIN_COMPILER) {
                            return $this->compileCoreTag('Internal_PluginCompiler', $args, $parameter, $tag);
                        } else {
                            return $this->compileCoreTag('Internal_Registered' . ucfirst($plugin_type), $args, $parameter, $tag);
                        }
                    }
                }
            } else {
                // compile closing tag of block function
                $base_tag = substr($tag, 0, - 5);
                // check if closing tag is a registered object
                if (isset($this->context->smarty->_registered['object'][$base_tag]) && isset($parameter['object_method'])) {
                    $method = $parameter['object_method'];
                    if (in_array($method, $this->context->smarty->_registered['object'][$base_tag][3])) {
                        return $this->compileCoreTag('Internal_ObjectBlockFunction', $args, $parameter, $tag, $method);
                    } else {
                        $this->error('unallowed closing tag method "' . $method . '" in registered object "' . $base_tag . '"', $this->lex->taglineno);
                    }
                }
                // registered compiler plugin ?
                if (isset($this->context->smarty->_registered['plugin'][Smarty::PLUGIN_COMPILER][$tag])) {
                    return $this->compileCoreTag('Internal_PluginCompilerclose', $args, $parameter, $tag);
                }
                // registered block tag ?
                if (isset($this->context->smarty->_registered['plugin'][Smarty::PLUGIN_BLOCK][$base_tag]) || isset($this->default_handler_plugins[Smarty::PLUGIN_BLOCK][$base_tag])) {
                    return $this->compileCoreTag('Internal_RegisteredBlock', $args, $parameter, $tag);
                }
                // block plugin?
                if ($function = $this->getPlugin($base_tag, Smarty::PLUGIN_BLOCK)) {
                    return $this->compileCoreTag('Internal_PluginBlock', $args, $parameter, $tag, $function);
                }
                if ($this->context->smarty->_loadPlugin('smarty_compiler_' . $tag)) {
                    return $this->compileCoreTag('Internal_PluginCompilerclose', $args, $parameter, $tag);
                }
                $this->error("Plugin '{{$tag}...}' not callable", $this->lex->taglineno);
            }
            $this->error("unknown tag '{{$tag}...}'", $this->lex->taglineno);
        }
    }

    /**
     * display compiler error messages
     * If parameter $args is empty it is a parser detected syntax error.
     * In this case the parser is called to obtain information about expected tokens.
     * If parameter $msg contains a string this is used as error message
     *
     * @param  string                  $msg  individual error message or null
     * @param  string                  $line line-number
     * @param  \Smarty_Compiler_Parser $parser
     *
     * @throws \Smarty_Exception_Compiler
     */
    public function error($msg = null, $line = null, $parser)
    {
        // get template source line which has error
        if (!isset($line)) {
            $line = $parser->lex->line;
        }
        throw new \Smarty_Exception_Compiler($msg, $line, $this->context, $parser);
    }

    /**
     * @param $context
     *
     * @return string
     */
    public function getDefaultNodeName($context)
    {
        return (string) $context->smarty->configuration->parser->defaultNode;
    }

    /**
     * Get node compiler object
     *
     * @param  \Smarty_Compiler_Node $node
     *
     * @return object node compiler object
     */
    public function getNodeCompilerCallback(\Smarty_Compiler_Node $node)
    {
        if (isset($node->base_tag)) {
            $nodeType = $node->base_tag;
        } elseif (isset($node->tag)) {
            $nodeType = $node->tag;
        } else {
            $nodeType = $node->nodeType;
        }
        $language = $node->language;
        // already in cache?
        if (isset($this->tag_cache[$language][$nodeType])) {
            return $this->tag_cache[$language][$nodeType];
        }
        // get compiler class postfix from node
        if (isset($node->compiler_class)) {
            $class = $configuration::$class_prefix . $node->compiler_class;
            if (class_exists($class) && method_exists($class, 'compile')) {
                return $this->tag_cache[$language][$nodeType] = array($class, 'compile');
            }
        }
        return $this->tag_cache[$language][$nodeType] = false;
    }

    /**
     * Check for plugins and return function name
     *
     * @param  string $plugin_name name of plugin or function
     * @param  string $plugin_type type of plugin
     *
     * @return string call name of function
     */
    public function getPlugin($plugin_name, $plugin_type)
    {
        $function = null;
        if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
            if (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            } elseif (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type] = $this->required_plugins['compiled'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'];
            }
        } else {
            if (isset($this->required_plugins['compiled'][$plugin_name][$plugin_type])) {
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            } elseif (isset($this->required_plugins['nocache'][$plugin_name][$plugin_type])) {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type] = $this->required_plugins['nocache'][$plugin_name][$plugin_type];
                $function = $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'];
            }
        }
        if (isset($function)) {
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }

            return $function;
        }
        // loop through plugin dirs and find the plugin
        $function = 'smarty_' . $plugin_type . '_' . $plugin_name;
        $file = $this->context->smarty->_loadPlugin($function, false);

        if (is_string($file)) {
            if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['nocache'][$plugin_name][$plugin_type]['function'] = $function;
            } else {
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['file'] = $file;
                $this->required_plugins['compiled'][$plugin_name][$plugin_type]['function'] = $function;
            }
            if ($plugin_type == 'modifier') {
                $this->modifier_plugins[$plugin_name] = true;
            }

            return $function;
        }
        if (is_callable($function)) {
            // plugin function is defined in the script
            return $function;
        }

        return false;
    }

    /**
     * Check for plugins by default plugin handler
     *
     * @param  string $tag         name of tag
     * @param  string $plugin_type type of plugin
     *
     * @return boolean true if found
     */
    public function getPluginFromDefaultHandler($tag, $plugin_type)
    {
        $callback = null;
        $script = null;
        $cacheable = true;
        $result = call_user_func_array(
            $this->context->smarty->default_plugin_handler_func, array($tag, $plugin_type, $this->context->smarty, &$callback, &$script, &$cacheable)
        );
        if ($result) {
            $this->tag_nocache = $this->tag_nocache || !$cacheable;
            if ($script !== null) {
                if (is_file($script)) {
                    if ($this->context->caching && ($this->nocache || $this->tag_nocache)) {
                        $this->required_plugins['nocache'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['nocache'][$tag][$plugin_type]['function'] = $callback;
                    } else {
                        $this->required_plugins['compiled'][$tag][$plugin_type]['file'] = $script;
                        $this->required_plugins['compiled'][$tag][$plugin_type]['function'] = $callback;
                    }
                    include_once $script;
                } else {
                    $this->error("Default plugin handler: Returned script file \"{$script}\" for \"{$tag}\" not found");
                }
            }
            if (!is_string($callback) && !(is_array($callback) && is_string($callback[0]) && is_string($callback[1]))) {
                $this->error("Default plugin handler: Returned callback for \"{$tag}\" must be a static function name or array of class and function name");
            }
            if (is_callable($callback)) {
                $this->default_handler_plugins[$plugin_type][$tag] = array($callback, true, array());

                return true;
            } else {
                $this->error("Default plugin handler: Returned callback for \"{$tag}\" not callable");
            }
        }

        return false;
    }

    /**
     * Return callback of template function for tag or false if not defined
     *
     * @param string $tag tag name
     *
     * @return bool
     */
    public function getTemplateFunctionCB($tag)
    {
        return false;
    }

    /**
     * Generate unique template class name
     *
     * @return string
     */
    public function getUniqueTemplateClassName()
    {
        return '_SmartyTemplate_' . str_replace(array('.', ','), '_', uniqid('', true));
    }

    /**
     * Get user registered node class name
     *
     * @param string $nodeName
     *
     * @return bool|string
     */
    public function getUserNodeClass($nodeName)
    {
        // TODO
        return false;
    }

    /**
     * Instance node
     *
     * @param  string        $name node name
     * @param \Smarty\Parser $parser
     *
     * @internal param Context $context
     * @internal param bool $error
     * @return \Smarty_Compiler_Node
     */
    public function instanceNode($name, \Smarty\Parser $parser)
    {
        return $parser->instanceNode($name);
    }

    /**
     * Instance parser class
     *
     * @param Context $context
     *
     * @return mixed
     */
    public function instanceParser(Context $context)
    {
        $parserClass = $context->getParserClass();
        if (method_exists($parserClass, 'instanceParser')) {
            return $parserClass::instanceParser($this, $context);
        }
        return new $parserClass($this, $context);
    }

    /**
     * Instance plugin tag node or false if not defined
     *
     * @param $tag
     *
     * @return string $tag   tag name
     */
    public function instancePluginTagNode($tag)
    {
        return false;
    }

    /**
     * @param Context $context
     *
     * @return Smarty_Template_Scope
     */
    public function instanceTemplateScope(Context $context)
    {
        return new Template\Scope($context);
    }

    /**
     * Restore output_var

     */
    public function popOutputVar()
    {
        $this->output_var = array_pop($this->output_var_stack);
    }

    /**
     * Push old output_var to stack and set up new one
     *
     * @param string $name new local variable name for rendered output
     */
    public function pushOutputVar($name)
    {
        array_push($this->output_var_stack, $this->output_var);
        $this->output_var = $name;
    }

    /**
     * Instance root node from context
     *
     * @param Context $context
     *
     * @return \Smarty_Node  root node
     * @throws \Smarty_Compiler_Exception_RootNodeNotFound
     */
    public function instanceRootNode(Context $context)
    {
        $parser = $this->instanceParser($context);
        if (false !== $node = $parser->instanceRootNode()) {
            return $node;
        }
        throw new \Smarty_Compiler_Exception_RootNodeNotFound($context->getSourceLanguage(), $context->source_compiled);
    }

    /**
     * Get parser class name
     *
     * @param null|Context $context
     * @param null         $sourceLanguage
     *
     * @throws \Smarty_Exception_ParserNotFound
     * @internal param null|string $sourceLanguageName
     * @return string lexer class name
     */
    public function getParserClass(Context $context, $sourceLanguage = null)
    {
        $sourceLanguage = isset($sourceLanguage) ? $sourceLanguage : $context->getSourceLanguage();
        // in cache?
        if (isset(self::$parser_class_cache[$sourceLanguage])) {
            return self::$parser_class_cache[$sourceLanguage];
        }
        $parserClass = $context->getParserClass();
        if (class_exists($parserClass)) {
            return self::$parser_class_cache[$sourceLanguage] = $parserClass;
        }
        throw new \Smarty_Exception_ParserNotFound($parserClass, $sourceLanguage);
    }
}