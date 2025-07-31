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
        $this->template->AddData("OOSCHART", Template::Load("oos-chart.html"));
    }

}
