<?php

class OOSChartModule implements IVisibleModuleBase
{

    private Template $template;

    public function GetTemplate(): \Template
    {
        return $this->template;
    }

    public function Run(array $data = []): void
    {
        $this->template = Template::Load("oos-chart.html");
        
    }
}