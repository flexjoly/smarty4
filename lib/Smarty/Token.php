<?php
namespace Smarty;
class Token
{
    const TEXT = 0;
    const NAME = 1;
    const NUMBER = 2;
    const STRING = 3;
    const OPERATOR = 8;
    protected $value;
    protected $type;
    protected $startpos;
    protected $endpos;
    protected $lineno;

    /**
     * Constructor.
     *
     * @param int    $type   The type of the token
     * @param string $value  The token value
     * @param int    $lineno The line position in the source
     */
    public function __construct($type, $value, $lineno)
    {
        $this->type = $type;
        $this->value = $value;
        $this->lineno = $lineno;
    }

    /**
     * Returns the english representation of a given type.
     *
     * @param int $type The type as an integer
     *
     * @return string The string representation
     */
    public static function typeToEnglish($type)
    {
        switch ($type) {
            case self::EOF_TYPE:
                return 'end of template';
            case self::TEXT_TYPE:
                return 'text';
            case self::BLOCK_START_TYPE:
                return 'begin of statement block';
            case self::VAR_START_TYPE:
                return 'begin of print statement';
            case self::BLOCK_END_TYPE:
                return 'end of statement block';
            case self::VAR_END_TYPE:
                return 'end of print statement';
            case self::NAME_TYPE:
                return 'name';
            case self::NUMBER_TYPE:
                return 'number';
            case self::STRING_TYPE:
                return 'string';
            case self::OPERATOR_TYPE:
                return 'operator';
            case self::PUNCTUATION_TYPE:
                return 'punctuation';
            case self::INTERPOLATION_START_TYPE:
                return 'begin of string interpolation';
            case self::INTERPOLATION_END_TYPE:
                return 'end of string interpolation';
            default:
                throw new LogicException(sprintf('Token of type "%s" does not exist.', $type));
        }
    }

    /**
     * Returns a string representation of the token.
     *
     * @return string A string representation of the token
     */
    public function __toString()
    {
        return sprintf('%s(%s)', self::typeToString($this->type, true), $this->value);
    }

    /**
     * Returns the constant representation (internal) of a given type.
     *
     * @param int  $type  The type as an integer
     * @param bool $short Whether to return a short representation or not
     *
     * @return string The string representation
     */
    public static function typeToString($type, $short = false)
    {
        switch ($type) {
            case self::EOF_TYPE:
                $name = 'EOF_TYPE';
                break;
            case self::TEXT_TYPE:
                $name = 'TEXT_TYPE';
                break;
            case self::BLOCK_START_TYPE:
                $name = 'BLOCK_START_TYPE';
                break;
            case self::VAR_START_TYPE:
                $name = 'VAR_START_TYPE';
                break;
            case self::BLOCK_END_TYPE:
                $name = 'BLOCK_END_TYPE';
                break;
            case self::VAR_END_TYPE:
                $name = 'VAR_END_TYPE';
                break;
            case self::NAME_TYPE:
                $name = 'NAME_TYPE';
                break;
            case self::NUMBER_TYPE:
                $name = 'NUMBER_TYPE';
                break;
            case self::STRING_TYPE:
                $name = 'STRING_TYPE';
                break;
            case self::OPERATOR_TYPE:
                $name = 'OPERATOR_TYPE';
                break;
            case self::PUNCTUATION_TYPE:
                $name = 'PUNCTUATION_TYPE';
                break;
            case self::INTERPOLATION_START_TYPE:
                $name = 'INTERPOLATION_START_TYPE';
                break;
            case self::INTERPOLATION_END_TYPE:
                $name = 'INTERPOLATION_END_TYPE';
                break;
            default:
                throw new LogicException(sprintf('Token of type "%s" does not exist.', $type));
        }

        return $short ? $name : 'Twig_Token::' . $name;
    }

    /**
     * Tests the current token for a type and/or a value.
     * Parameters may be:
     * * just type
     * * type and value (or array of possible values)
     * * just value (or array of possible values) (NAME_TYPE is used as type)
     *
     * @param array|int         $type   The type to test
     * @param array|string|null $values The token value
     *
     * @return bool
     */
    public function test($type, $values = null)
    {
        if (null === $values && !is_int($type)) {
            $values = $type;
            $type = self::NAME_TYPE;
        }

        return ($this->type === $type) && (
            null === $values ||
            (is_array($values) && in_array($this->value, $values)) ||
            $this->value == $values
        );
    }

    /**
     * Gets the line.
     *
     * @return int     The source line
     */
    public function getLine()
    {
        return $this->lineno;
    }

    /**
     * Gets the token type.
     *
     * @return int     The token type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Gets the token value.
     *
     * @return string The token value
     */
    public function getValue()
    {
        return $this->value;
    }
}