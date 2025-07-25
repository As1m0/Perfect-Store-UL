<?php

enum LogLevel : int
{
    case Info = 0;
    case Warning = 1;
    case Error = 2;
}

enum DBTypes : string
{
    case Int = "i";
    case Double = "d";
    case String = "s";
    case Blob = "b";
}