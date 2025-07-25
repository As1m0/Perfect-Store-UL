<?php

class DBParam
{
    private DBTypes $type;
    private mixed $param;
    
    public function getType(): DBTypes
    {
        return $this->type;
    }

    public function getParam(): mixed
    {
        return $this->param;
    }
    
    public function __construct(DBTypes $type, mixed $param)
    {
        $this->type = $type;
        $this->param = $param;
    }
    
    public function __toString(): string
    {
        return "DBParam[type=" . $this->type
                . ", param=" . $this->param
                . "]";
    }
}
