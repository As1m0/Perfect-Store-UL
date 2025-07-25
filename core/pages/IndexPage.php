<?php

class IndexPage implements IPageBase
{
    private Template $template;
    
    public function GetTemplate(): Template
    {
        return $this->template;
    }

    public function Run(array $pageData): void
    {
        $this->template = Template::Load($pageData["template"]);
        try
        {
            $this->template->AddData("TITLE", Model::LoadText($pageData["page"], "title")["text"]);
            $this->template->AddData("WELCOME", Model::LoadText($pageData["page"], "welcome")["text"]);
        }
        catch (Exception $ex)
        {
            return;
        }
    }
}
