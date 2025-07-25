<?php

class Template
{
    private string $rawHtml; //A nyers HTML állomány - ezt olvassuk be
    private array $flagData; //Ez egy olyan asszociatív tömb lesz, amiben a kulcsok maguk a flag-ek lesznek, de az egyes kulcsokhoz egy tömbnyi adat tartozik - amit az adott flag-re kell sorrendben bepakolni
    private array $previousRenders;
    private bool $modified;


    public function getFlags() : array
    {
        return array_keys($this->flagData);
    }
    
    public function getOneFlagData(string $flag) : array
    {
        if(array_key_exists($flag, $this->flagData))
        {
            return $this->flagData[$flag];
        }
        throw new TemplateException("A megadott flag ($flag) nem található a template-ben!");
    }

    public function getPreviousRenders(): array
    {
        return $this->previousRenders;
    }

    private function __construct(string $template)
    {
        global $cfg;
        if(trim($template) == "")
        {
            throw new TemplateException("A template alapját képzó szöveges tartalom nem lehet üres!");
        }
        $this->rawHtml = $template;
        $this->modified = true;
        $this->previousRenders = array();
        if(preg_match_all($cfg["templateFlag"], $this->rawHtml, $flags) !== false)
        {
            $this->flagData = array();
            foreach ($flags[1] as $flag)
            {
                $this->flagData[$flag] = array();
            }
        }
        else
        {
            throw new TemplateException("A keresendő template flag formátuma hibás!");
        }
    }
    
    public function AddData(string $flag, string|Template $data) : void
    {
        if(array_key_exists($flag, $this->flagData))
        {
            $this->flagData[$flag][] = $data;
            $this->modified = true;
            return;
        }
        throw new TemplateException("A keresett flag ($flag) nem található a template-ben!");
    }
    
    public function Render($force = false) : string
    {
        if($this->modified || $force)
        {
            //print("ok");
            $html = $this->rawHtml;
            foreach ($this->flagData as $flag=>$data)
            {
                $flagContent = "";
                foreach ($data as $item)
                {
                    if(is_a($item, "Template"))
                    {
                        $item = $item->Render($force);
                    }
                    $flagContent .= $item;
                }
                $html = str_replace("%!$flag!%", $flagContent, $html);
            }
            if(count($this->previousRenders) == 3)
            {
                array_shift($this->previousRenders);
            }
            $this->previousRenders[] = $html;
            $this->modified = false;
        }
        return $this->previousRenders[count($this->previousRenders)-1];
    }
    
    public static function Parse(string $template) : Template
    {
        return new Template($template);
    }
    
    public static function Load(string $filename) : Template
    {
        global $cfg;
        if(!file_exists($cfg["templateFolder"]."/".$filename) || !is_readable($cfg["templateFolder"]."/".$filename))
        {
            throw new TemplateException("A megadott template forrás ($filename) nem található / olvasható!");
        }
        $template = file_get_contents($cfg["templateFolder"]."/".$filename);
        return new Template($template);
    }
}
