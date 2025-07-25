<?php

abstract class View
{
    private static Template $baseTemplate;
    
    public static function getBaseTemplate(): Template
    {
        return self::$baseTemplate;
    }

    public static function setBaseTemplate(Template $baseTemplate): void
    {
        self::$baseTemplate = $baseTemplate;
    }
    
    public static function Init(Template $baseTemplate) : void
    {
        self::$baseTemplate = $baseTemplate;
    }
    
    public static function PrintFinalTemplate(array $headers = null) : void
    {
        global $cfg;
        if($headers != null)
        {
            foreach ($headers as $value)
            {
                header($value);
            }
        }
        header("Content-type: ".$cfg["defaultContentType"]);
        print(self::$baseTemplate->Render(true));
    }
}
