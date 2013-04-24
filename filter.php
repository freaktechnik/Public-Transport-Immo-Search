<?php
class Filter {
    private filterProperties;
    
    function __construct() {
        $this->filterProperties = array();
    }
    
    private function hasProperty($name) {
        return array_key_exists($name,$this->filterProperties);
    }
    
    public function addProperty($type,$name,$target) {
        $this->filterProperties[$name] = new FilterProperty($type,$target);
    }
    
    public function setProperty($name,$value,$type,$target) {
        if(!$this->hasProperty($name)) {
            $this->addProperty($type,$name,$target);
        }
        $this->filterProperties[$name]->setValue($value);
    }
    
    public function getProperty($name) {
        return $this->filterProperties[$name];
    }
    
    public function getComparisRequest() {
        return base64_encode(json_encode($this->getArray('comparis')));
    }
    
    private function getArrayForTarget($target) {
        return array_filer($this->filterProperties,create_function('$property','return $property->hasTarget($target);'));
    }
    
    public function getArray($target) {
        return array_map(create_function('$property','return $property->getValue();'),$target?$this->getArray():$this->filterProperties);
    }

}

class FilterProperty {
    private target
    private value
    private type
    
    const STRING = 'string';
    const BOOL = 'boolean';
    const INTEGER = 'int';
    const DATE = 'date';
    
    public
    
    function __construct($type,$target) {
        $this->type = $type;
        $this->target = $target;
        
        if($this->type == self::DATE)
    }
    
    public function setValue($value) {
        if($this->type==self::STRING&&is_string($value)) {
            $this->value = $value;
        }
        else if($this->type==self::BOOL&&is_bool($value)) {
            $this->value = $value;
        }
        else if($this->type==self::INTEGER&&is_int($value)) {
            $this->value = $value;
        }
        else if($this->type == self::DATE) {
            if(is_int($value)) {
                $this->value = date("Y-m-d\TH:i:s",$value);
            }
            else if(is_string($value)) {
                $this->value = $value;
            }
        }
    }
    
    public function getValue() {
        return $this->value;
    }
    
    /*public function getTarget() {
        return $this->target;
    }*/
    public function hasTarget($target) {
        return $this->target == $target;
    }
}

?>