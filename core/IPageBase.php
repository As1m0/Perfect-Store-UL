<?php

interface IPageBase
{
    function Run(array $pageData) : void;
    function GetTemplate() : Template;
}
