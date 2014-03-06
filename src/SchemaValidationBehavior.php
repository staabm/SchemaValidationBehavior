<?php

/**
 * Automatically generates validators to enforce the actual db-schema on ORM side,
 * instead of disturbing the DB with content which is not valid as far as the schema rules are concerned. 
 *
 * @author     Markus Staab
 * @package    cwsPropel.behaviour
 */
class SchemaValidationBehavior extends Behavior
{
    // default parameters values
    protected $parameters = array(
        'required_message'      => '${colname} is required',
        'max_length_message'      => '${colname} cannot be larger than ${value} chars',
        'match_integer_message'      => '${colname} must be an integer number',
        'match_float_message'      => '${colname} must be a floating number',
        'match_email_message'      => '${colname} must be a valid email address',
    );
    
    private static $INTEGER_PROPEL_TYPES = array(
        PropelTypes::SMALLINT, PropelTypes::TINYINT, PropelTypes::INTEGER, PropelTypes::BIGINT
    );
    
    private static $FLOATING_PROPEL_TYPES = array(
        PropelTypes::FLOAT, PropelTypes::DOUBLE, PropelTypes::NUMERIC, PropelTypes::DECIMAL, PropelTypes::REAL
    );
    
    public function modifyTable()
    {
        /** @var $table Table */
        $table = $this->getTable();
        foreach($table->getColumns() as $column) {
            /** @var $column Column */

            $validator = new Validator();
            if ($column->isNotNull()) {
                $rule = new Rule();
                $rule->setName('required');
                $rule->setMessage($this->parseMessage($this->getParameter('required_message'), $column));
                
                $validator->addRule($rule);
            }
            
            if (PropelTypes::isTextType($column->getType()) && $column->getSize()) {
                $rule = new Rule();
                $rule->setName('maxLength');
                $rule->setValue($column->getSize());
                $rule->setMessage($this->parseMessage($this->getParameter('max_length_message'), $column));
                
                $validator->addRule($rule);
            }
            
            if (self::isIntegerType($column->getType())) {
                // size is per default optional
                $size = $this->nonZeroOrEmptyString($column->getSize());
                $pattern = '/^-?[0-9]{1,%s}$/';
                
                $rule = new Rule();
                $rule->setName('match');
                $rule->setValue(sprintf($pattern, $size));
                $rule->setMessage($this->parseMessage($this->getParameter('match_integer_message'), $column));
                
                $validator->addRule($rule);
            }
            else if (self::isFloatingType($column->getType())) {
                $size = $this->nonZeroOrEmptyString($column->getSize());
                $scale = $this->nonZeroOrEmptyString($column->getScale());
                
                $numIntBeforeDecimal = $this->nonZeroOrEmptyString($size - $scale);
                $pattern = '/^-?(?:[0-9]{1,%s}|[0-9]{0,%s}\.[0-9]{1,%s})$/';
                
                $rule = new Rule();
                $rule->setName('match');
                $rule->setValue(sprintf($pattern, $numIntBeforeDecimal, $numIntBeforeDecimal, $scale));
                $rule->setMessage($this->parseMessage($this->getParameter('match_float_message'), $column));
                
                $validator->addRule($rule);
            }
            
            if (stripos($column->getName(), 'email') !== false) {
                $rule = new Rule();
                $rule->setName('match');
//                 $rule->setValue('/^(?:[a-zA-Z0-9])+(?:[\.a-zA-Z0-9_-])*@(?:[a-zA-Z0-9])+(?:\.[a-zA-Z0-9_-]+)+$/');
                $rule->setValue('/^(?:[a-zA-Z0-9_-])+(?:[\.a-zA-Z0-9_-])*@(?:[.a-zA-Z0-9_-])+(?:\.[a-zA-Z0-9]+)+$/');
                $rule->setMessage($this->parseMessage($this->getParameter('match_email_message'), $column));
                
                $validator->addRule($rule);
            }
            
            if (count($validator->getRules()) > 0) {
                $validator->setColumn($column);
                $table->addValidator($validator);
            }
        }
    }
    
    public static function isIntegerType($propelType) {
        return in_array($propelType, self::$INTEGER_PROPEL_TYPES);
    }
    
    public static function isFloatingType($propelType) {
        return in_array($propelType, self::$FLOATING_PROPEL_TYPES);
    }
    
    private function parseMessage($message, Column $column) {
        $message = strtr($message, array('${colname}' => $column->getPhpName()));
        return $message;
    }
    
    private function nonZeroOrEmptyString($val) {
        if ($val && $val > 0) {
            return $val;
        }
        return '';
    }
}
