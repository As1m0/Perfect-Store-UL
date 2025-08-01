<?php

class LoginPage implements IPageBase
{
    private Template $template;
    
    public function GetTemplate(): Template
    {
        return $this->template;
    }

    public function Run(array $pageData): void
    {
        $this->template = Template::Load($pageData["template"]);

        $this->template->AddData("DISPLAY", "none");  

        if(isset($_POST["login"]))
        {
            if(isset($_POST["email"]) && trim($_POST["email"]) != "" && isset($_POST["pass"]) && trim($_POST["pass"]) != "")
            {
                if(filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL))
                {
                    $email = htmlspecialchars(trim($_POST["email"]));
                    $pass = hash("sha256", trim($_POST["pass"]));

                    if (Model::Login($email, $pass))
                    {
                        $result["login"]["info"] = "Logged in successfully!";
                        $result["login"]["success"] = true;
                    }
                    else
                    {
                        $result["login"]["info"] = "Wrong acccount / password!";
                        $result["login"]["success"] = false;
                    }
                }
            }
            else
            {
                $result["login"]["info"] = "Missing data!";
                $result["login"]["success"] = false;
            }
        }

        global $cfg;

        if(isset($result["login"])){
            $this->template->AddData("RESULT", $result["login"]["info"]);
            $this->template->AddData("DISPLAY", "block");  
            if(isset($result["login"]["success"]) && $result["login"]["success"] !== false)
            {
                $this->template->AddData("COLOR", "green");
                $this->template->AddData("SCRIPT", "<script>window.setTimeout(function(){window.location.href='{$cfg["mainPage"]}.php';}, 1500);</script>");
            } else
            {
                $this->template->AddData("COLOR", "red");
                $this->template->AddData("SCRIPT", "<script>window.setTimeout(function(){window.location.href='{$cfg["mainPage"]}.php?p=login';}, 1500);</script>");
            }
        }

    }
}