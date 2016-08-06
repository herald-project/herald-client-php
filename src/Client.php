<?php

namespace Herald\Client;

// use GuzzleHttp\Post\PostFile;
use GuzzleHttp\Client as GuzzleClient;

class Client implements MessageSenderInterface
{
    private $apiUrl;
    private $baseUrl;
    private $username;
    private $password;
    private $transportAccount;
    private $templateNamePrefix = '';
    private $toAddressOverride = null;
    private $account;
    private $library;

    public function __construct($username, $password, $apiUrl, $account, $library, $transportAccount)
    {
        $this->username = $username;
        $this->password = $password;
        $this->apiUrl = $apiUrl;
        $this->account = $account;
        $this->library = $library;
        $this->transportAccount = $transportAccount;
        $this->baseUrl = $apiUrl.'/'.$account.'/'.$library;
    }

    public function setTemplateNamePrefix($prefix)
    {
        $this->templateNamePrefix = $prefix;

        return $this;
    }

    public function setToAddressOverride($address)
    {
        $this->toAddressOverride = $address;

        return $this;
    }

    public function send(MessageInterface $message, $skipNamePrefix = false)
    {
        $guzzleclient = new GuzzleClient();

        $url = $this->baseUrl.'/send/';
        $url .= $this->transportAccount.'/';
        $url .= $this->patchTemplateName($message->getTemplate(), $skipNamePrefix);
        $url .= '?to='.($this->toAddressOverride ? $this->toAddressOverride : $message->getToAddress());

        $res = $guzzleclient->post($url, [
            'auth' => [$this->username, $this->password],
            'headers' => ['content-type' => 'application/json'],
            'body' => $message->serializeData(),
        ]);

        return ($res->getStatusCode() == 200);
    }

    public function preview(MessageInterface $message, $skipNamePrefix = false)
    {
        $guzzleclient = new GuzzleClient();

        $url = $this->baseUrl.'/preview/';
        $url .= $this->patchTemplateName($message->getTemplate(), $skipNamePrefix);
        $url .= '?to='.$message->getToAddress();

        $res = $guzzleclient->post($url, [
            'auth' => [$this->username, $this->password],
            'headers' => ['content-type' => 'application/json'],
            'body' => $message->serializeData(true),
        ]);

        return json_decode($res->getBody());
    }

    public function checkTemplate($templateName)
    {
        return $this->templateExists($templateName);
    }

    public function templateExists($templateName, $skipNamePrefix = false)
    {
        $guzzleclient = new GuzzleClient();

        $url = $this->baseUrl.'/checktemplate/'.$this->patchTemplateName($templateName, $skipNamePrefix);

        $res = $guzzleclient->post($url, [
            'auth' => [$this->username, $this->password],
        ]);

        $body = $res->getBody();
        if ($body) {
            if ($body->read(2) == 'ok') {
                return true;
            }
        }

        return false;
    }

    private function patchTemplateName($templateName, $skipNamePrefix = false)
    {
        if ($skipNamePrefix) {
            return $this->escapeTemplateName($templateName);
        } else {
            return $this->escapeTemplateName($this->templateNamePrefix.$templateName);
        }
    }

    private function escapeTemplateName($templateName)
    {
        return str_replace('/', '___', $templateName);
    }

    public function getMessages()
    {
        $guzzleclient = new GuzzleClient();

        $url = $this->baseUrl.'/messages';

        $res = $guzzleclient->post($url, [
            'auth' => [$this->username, $this->password],
        ]);

        $body = $res->getBody();
        if ($body) {
            if ($body->read(2) == 'ok') {
                return true;
            }
        }
        $content = (string) $body;
        $data = json_decode($content, true);
        //print_r($data);

        $messages = array();

        foreach ($data['items'] as $m) {
            $message = $this->arrayToMessage($m);
            $messages[] = $message;
        }

        return $messages;
    }

    public function getMessageById($messageId)
    {
        $guzzleclient = new GuzzleClient();

        $url = $this->baseUrl.'/messages/'.$messageId;

        $res = $guzzleclient->post($url, [
            'auth' => [$this->username, $this->password],
        ]);

        $body = $res->getBody();
        if ($body) {
            if ($body->read(2) == 'ok') {
                return true;
            }
        }
        $content = (string) $body;
        $data = json_decode($content, true);
        //print_r($data);

        $message = $this->arrayToMessage($data);

        return $message;
    }

    // -----------------------------------------------------------
    
    private function doQuery($method, $url, $data = null)
    {
        $guzzleclient = new GuzzleClient();

        if(stristr($method, 'get') !== false) {
            $res = $guzzleclient->get($this->baseUrl.'/'.$url, [
                'auth' => [$this->username, $this->password],
            ]);
        } else {
            $res = $guzzleclient->post($this->baseUrl.'/'.$url, [
                'auth' => [$this->username, $this->password],
                'json' => $data,
            ]);
        }
        
        return json_decode($res->getBody(), true);
    }

    public function getLists()
    {
        return $this->doQuery('GET', 'list') ;
    }
    
    public function getContacts($listId)
    {
        return $this->doQuery('GET', 'list/'.$listId) ;
    }
    
    public function getListFields($listId)
    {
        return $this->doQuery('GET', 'list/'.$listId.'/list_field') ;
    }

    public function getSegments($listId)
    {
        return $this->doQuery('GET', 'list/'.$listId.'/segment') ;
    }

    public function viewContact($contactId)
    {
        return $this->doQuery('GET', 'contact/'.$contactId) ;
    }

    public function deleteContact($contactId)
    {
        return $this->doQuery('GET', 'contact/'.$contactId.'/delete') ;
    }

    public function addContact($listId, $address)
    {
        return $this->doQuery('POST', 'contact/add', [
            'listId' => $listId, 
            'address' => $address
        ]);
    }
    
    public function getContactProperties($contactId)
    {
        return $this->doQuery('GET', 'contact/'.$contactId.'/contact_property') ;
    }

    public function deleteProperty($propertyId)
    {
        return $this->doQuery('GET', 'contact_property/'.$propertyId.'/delete') ;
    }

    public function addProperty($contactId, $listFieldId, $value)
    {
        return $this->doQuery('POST', 'contact_property/add', [
            'listFieldId' => $listFieldId, 
            'contactId' => $contactId,
            'value' => $value,
        ]) ;
    }
    
    public function sendList($listId, $segmentId, $messageTemplateId)
    {
        return $this->doQuery('POST', 'send/'.$listId, [
            'segmentId' => $segmentId, 
            'messageTemplateId' => $messageTemplateId,
        ]) ;
    }
    
    // -----------------------------------------------------------
    private function arrayToMessage($m)
    {
        $message = new Message();
        $message->setId($m['id']);
        $message->setUuid($m['uuid']);
        $message->setUser($m['user']);
        $message->setStamp($m['stamp']);
        $message->setStatus($m['status']);
        $message->setFromAddress($m['fromAddress']);
        $message->setFromName($m['fromName']);

        $addresses = $this->parseAddresses($m['to']);
        $message->setTo($addresses);

        $addresses = $this->parseAddresses($m['cc']);
        $message->setCc($addresses);

        $addresses = $this->parseAddresses($m['bcc']);
        $message->setBcc($addresses);

        $message->setSubject($m['subject']);

        if (isset($m['template'])) {
            $t = $m['template'];
            $template = new Template();
            $template->setId($t['id']);
            //$template->setUuid($m['template']['uuid']);
            $template->setCode($t['code']);
            $template->setUser($t['user']);
            $template->setStatus($t['status']);
            $template->setFromAddress($t['fromEmail']);
            $template->setFromName($t['fromName']);

            $addresses = $this->parseAddresses($t['cc']);
            $template->setCc($addresses);

            $addresses = $this->parseAddresses($t['bcc']);
            $template->setBcc($addresses);

            $template->setBody($t['body']);
            $template->setBodyMarkup($t['bodyMarkup']);

            if (isset($t['layout'])) {
                $l = $t['layout'];
                $layout = new Layout();
                $layout->setId($l['id']);
                $layout->setCode($l['code']);
                $layout->setBody($l['body']);
                $template->setLayout($layout);
            }
            $message->setTemplate($template);
        }

        return $message;
    }

    /*
     * Current server returns a JSON string, not actual array data
     * This method detects strings and decodes them
     * Then it instantiates a new Address object for every item and returns
     * an array of Address objects
     */
    private function parseAddresses($addresses)
    {
        if (!$addresses) {
            return array();
        }
        if (is_string($addresses)) {
            // old format json string, parse to array
            if ($addresses[0] != '[') {
                // Expecting a json array, assuming single address
                $address = new Address();
                //todo: parse in id, uuid and type if set
                $address->setIdentifier($addresses);
                $address->setName('');
                $address->setType('email');

                return array($address);
            }
            $addresses = json_decode($addresses, true);
        }

        $res = array();
        foreach ($addresses as $a) {
            $address = new Address();
            //todo: parse in id, uuid and type if set
            $address->setIdentifier($a['identifier']);
            $address->setName($a['name']);
            $address->setType('email');
            $res[] = $address;
        }

        return $res;
    }
}
