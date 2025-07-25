<?php

class Mailer
{
    private string $message;
    private string $from;
    private string $to;
    private string $subject;
    private string $cc;

    private function setMessage(string $message): void
    {
        if($message != "") {
            $this->message = $message;
        } else {
            throw new Exception("Hibás üzenet!");
        }
    }

    private function setSubject(string $subject): void
    {
        if($subject != "") {
            $this->subject = $subject;
        } else {
            throw new Exception("Hibás üzenet téma!");
        }
    }

    private function setTo(string $to): void
    {
        if(filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->to = $to;
        } else {
            throw new Exception("Hibás email cím formátum!");
        }
    }

    private function setFrom(string $from): void
    {
        if(filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $this->from = $from;
        } else {
            throw new Exception("Hibás email cím formátum!");
        }
    }

    public function __construct(string $message, string $from, string $to, string $subject, string $cc = "")
    {
        $this->setMessage($message);
        $this->setFrom($from);
        $this->setTo($to);
        $this->setSubject($subject);
        if($cc != "") {
            $this->setCC($cc);
        }
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getCC(): string
    {
        return $this->cc;
    }

    public function setCC(string $cc): void
    {
        if(filter_var($cc, FILTER_VALIDATE_EMAIL)) {
            $this->cc = $cc;
        } else {
            throw new Exception("Hibás CC email cím!");
        }
    }

    public function addAttachment(string $filePath): void
    {
        if(file_exists($filePath)) {
            $this->attachment = $filePath;
        } else {
            throw new Exception("A csatolandó fájl nem található");
        }
    }

    public function send(): bool
    {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mailer->From = $this->from;
            $mailer->addAddress($this->to);
            if(isset($this->cc)) {
                $mailer->addCC($this->cc);
            }
            $mailer->Subject = $this->subject;
            $mailer->Body = "<h2>{$this->message}</h2>";
            $mailer->CharSet = "utf-8";
            $mailer->isHTML(true);
            if(isset($this->attachment)) {
                $mailer->addAttachment($this->attachment);
            }
            $mailer->send();
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
}
