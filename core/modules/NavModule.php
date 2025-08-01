<?php

class NavModule implements IVisibleModuleBase
{

    private Template $template;

    public function GetTemplate(): \Template
    {
        return $this->template;
    }

    public function Run(array $data = []): void
    {
        $this->template = Template::Load("navigation.html");

        if(isset($_SESSION["usermail"]))
        {
            $this->template->AddData("USERMAIL", $_SESSION["usermail"]);
        }
    }
}