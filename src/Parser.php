<?php

namespace Durlecode\EJSParser;

use DOMDocument;
use DOMText;
use Exception;
use Masterminds\HTML5;
use StdClass;

class Parser
{
    /**
     * @var StdClass
     */
    private $data;

    /**
     * @var DOMDocument
     */
    private $dom;

    /**
     * @var HTML5
     */
    private $html5;

    /**
     * @var string
     */
    private $prefix = "prs";

    /**
     * @var array
     */
    public $customImgAttrs = [
        'border border-alhi-200',
    ];

    public function __construct(string $data)
    {
        $this->data = json_decode($data);

        $this->dom = new DOMDocument(1.0, 'UTF-8');

        $this->html5 = new HTML5([
            'target_document' => $this->dom,
            'disable_html_ns' => true
        ]);
    }

    static function parse($data)
    {
        return new self($data);
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getTime()
    {
        return isset($this->data->time) ? $this->data->time : null;
    }

    public function getVersion()
    {
        return isset($this->data->version) ? $this->data->version : null;
    }

    public function getBlocks()
    {
        return isset($this->data->blocks) ? $this->data->blocks : null;
    }

    public function toHtml()
    {
        $this->init();

        return $this->dom->saveHTML();
    }

    /**
     * @throws Exception
     */
    private function init()
    {
        if (!$this->hasBlocks()) throw new Exception('No Blocks to parse !');
        foreach ($this->data->blocks as $block) {


                $methodName = 'parse'.ucfirst($block->type);
                if (method_exists($this, $methodName)) {
                    $this->{$methodName}();
                } else {
                    //else can be removed to not throw error
                    throw new Exception('Unknow block, unable to parse');
                }
        }
    }

    private function hasBlocks()
    {
        return count($this->data->blocks) !== 0;
    }

    private function parseHeader($block)
    {
        $text = new DOMText($block->data->text);

        $header = $this->dom->createElement('h' . $block->data->level);

        $header->setAttribute('class', "{$this->prefix}-h{$block->data->level}");

        $header->appendChild($text);

        $this->dom->appendChild($header);
    }

    private function parseDelimiter()
    {
        $node = $this->dom->createElement('hr');

        $node->setAttribute('class', "{$this->prefix}-delimiter");

        $this->dom->appendChild($node);
    }

    private function parseCode($block)
    {
        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', "{$this->prefix}-code");

        $pre = $this->dom->createElement('pre');

        $code = $this->dom->createElement('code');

        $content = new DOMText($block->data->code);

        $code->appendChild($content);

        $pre->appendChild($code);

        $wrapper->appendChild($pre);

        $this->dom->appendChild($wrapper);
    }

    private function parseParagraph($block)
    {
        $node = $this->dom->createElement('p');

        $node->setAttribute('class', "{$this->prefix}-paragraph");

        $node->appendChild($this->html5->loadHTMLFragment($block->data->text));

        $this->dom->appendChild($node);
    }

    private function parseLink($block)
    {
        $link = $this->dom->createElement('a');

        $link->setAttribute('href', $block->data->link);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('class', "{$this->prefix}-link");

        $innerContainer = $this->dom->createElement('div');
        $innerContainer->setAttribute('class', "{$this->prefix}-link-container");

        $hasTitle = isset($block->data->meta->title);
        $hasDescription = isset($block->data->meta->description);
        $hasImage = isset($block->data->meta->image);

        if ($hasTitle) {
            $titleNode = $this->dom->createElement('div');
            $titleNode->setAttribute('class', "{$this->prefix}-link-title");
            $titleText = new DOMText($block->data->meta->title);
            $titleNode->appendChild($titleText);
            $innerContainer->appendChild($titleNode);
        }

        if ($hasDescription) {
            $descriptionNode = $this->dom->createElement('div');
            $descriptionNode->setAttribute('class', "{$this->prefix}-link-description");
            $descriptionText = new DOMText($block->data->meta->description);
            $descriptionNode->appendChild($descriptionText);
            $innerContainer->appendChild($descriptionNode);
        }

        $linkContainer = $this->dom->createElement('div');
        $linkContainer->setAttribute('class', "{$this->prefix}-link-url");
        $linkText = new DOMText($block->data->link);
        $linkContainer->appendChild($linkText);
        $innerContainer->appendChild($linkContainer);

        $link->appendChild($innerContainer);

        if ($hasImage) {
            $imageContainer = $this->dom->createElement('div');
            $imageContainer->setAttribute('class', "{$this->prefix}-link-img-container");
            $image = $this->dom->createElement('img');
            $image->setAttribute('src', $block->data->meta->image->url);
            $imageContainer->appendChild($image);
            $link->appendChild($imageContainer);
            $innerContainer->setAttribute('class', "{$this->prefix}-link-container-with-img");
        }

        $this->dom->appendChild($link);
    }

    private function parseEmbed($block)
    {
        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', "{$this->prefix}-embed");

        switch ($block->data->service) {
            case 'youtube':

                $attrs = [
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                    'allow' => 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
                    'allowfullscreen' => true
                ];

                $wrapper->appendChild($this->createIframe($attrs));

                break;
            case 'codepen' || 'gfycat':

                $attrs = [
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                ];

                $wrapper->appendChild($this->createIframe($attrs));

                break;
        }

        $this->dom->appendChild($wrapper);
    }

    private function createIframe(array $attrs)
    {
        $iframe = $this->dom->createElement('iframe');

        foreach ($attrs as $key => $attr) $iframe->setAttribute($key, $attr);

        return $iframe;
    }

    private function parseRaw($block)
    {
        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', "{$this->prefix}-raw");

        $wrapper->appendChild($this->html5->loadHTMLFragment($block->data->html));

        $this->dom->appendChild($wrapper);
    }

    private function parseList($block)
    {
        $wrapper = $this->dom->createElement('div');
        $wrapper->setAttribute('class', "{$this->prefix}-list");

        $list = null;

        switch ($block->data->style) {
            case 'ordered':
                $list = $this->dom->createElement('ol');
                break;
            default:
                $list = $this->dom->createElement('ul');
                break;
        }

        foreach ($block->data->items as $item) {
            $li = $this->dom->createElement('li');
            $li->appendChild($this->html5->loadHTMLFragment($item));
            $list->appendChild($li);
        }

        $wrapper->appendChild($list);

        $this->dom->appendChild($wrapper);
    }

    private function parseWarning($block)
    {
        $title = new DOMText($block->data->title);
        $message = new DOMText($block->data->message);

        $wrapper = $this->dom->createElement('div');
        $wrapper->setAttribute('class', "{$this->prefix}-warning");

        $textWrapper = $this->dom->createElement('div');
        $titleWrapper = $this->dom->createElement('p');

        $titleWrapper->appendChild($title);
        $messageWrapper = $this->dom->createElement('p');

        $messageWrapper->appendChild($message);

        $textWrapper->appendChild($titleWrapper);
        $textWrapper->appendChild($messageWrapper);

        $icon = $this->dom->createElement('ion-icon');
        $icon->setAttribute('name', 'information-outline');
        $icon->setAttribute('size', 'large');

        $wrapper->appendChild($icon);
        $wrapper->appendChild($textWrapper);

        $this->dom->appendChild($wrapper);
    }

    private function parseSimpleImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $figure->setAttribute('class', "{$this->prefix}-image");

        $img = $this->dom->createElement('img');

        $imgAttrs = [];

        if ($block->data->withBorder) $imgAttrs[] = "{$this->prefix}-image-border";
        if ($block->data->withBackground) $imgAttrs[] = "{$this->prefix}-image-background";
        if ($block->data->stretched) $imgAttrs[] = "{$this->prefix}-image-stretched";
        $imgAttrs = array_merge($imgAttrs, $this->customImgAttrs);

        $img->setAttribute('src', $block->data->url);
        $img->setAttribute('class', implode(' ', $imgAttrs));

        $figCaption = $this->dom->createElement('figcaption');

        $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));

        $figure->appendChild($img);

        $figure->appendChild($figCaption);

        $this->dom->appendChild($figure);
    }

    private function parseImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $figure->setAttribute('class', "{$this->prefix}-image");

        $img = $this->dom->createElement('img');

        $imgAttrs = [];

        if ($block->data->withBorder) $imgAttrs[] = "{$this->prefix}-image-border";
        if ($block->data->withBackground) $imgAttrs[] = "{$this->prefix}-image-background";
        if ($block->data->stretched) $imgAttrs[] = "{$this->prefix}-image-stretched";
        $imgAttrs = array_merge($imgAttrs, $this->customImgAttrs);

        $img->setAttribute('src', $block->data->file->url);
        $img->setAttribute('class', implode(' ', $imgAttrs));

        $figure->appendChild($img);

        if ($block->data->caption) {

            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseQuote($block)
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', "{$this->prefix}-quote");

        $blockquote = $this->dom->createElement('blockquote');

        $blockquote->setAttribute('class', "{$this->prefix}-blockquote");
        $blockquote->appendChild($this->html5->loadHTMLFragment($block->data->text));
        $figure->appendChild($blockquote);

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseTable($block)
    {
        $table = $this->dom->createElement('table');
        $table->setAttribute('class', "{$this->prefix}-table");

        $tr_top = $this->dom->createElement('tr');
        $thead = $this->dom->createElement('thead');
        $tbody = $this->dom->createElement('tbody');
        $thead->appendChild($tr_top);
        $table->appendChild($thead);
        $table->appendChild($tbody);



        foreach ($block->data->content[0] as $head) {
            $th = $this->dom->createElement('th', $head);
            $tr_top->appendChild($th);
        }

        $dataset = $block->data->content;
        unset($dataset[0]);

        foreach ($dataset as $data) {
            $tr = $this->dom->createElement('tr');
            foreach ($data as $item) {
                $td = $this->dom->createElement('td', $item);
                $tr->appendChild($td);
            }
            $tbody->appendChild($tr);
        }


        $this->dom->appendChild($table);
    }

    private function parseLinkTool($block)
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', "{$this->prefix}-main-link");

        $link = $this->dom->createElement('a');
        $link->setAttribute('class', "{$this->prefix}-link");
        $link->setAttribute('href', $block->data->link);

        $img = $this->dom->createElement('img');
        $img->setAttribute('src', $block->data->meta->image->url);

        $link->appendChild($img);

        $link_title = $this->dom->createElement('a');
        $link_title->setAttribute('class', "{$this->prefix}-link-title");
        $link_title->setAttribute('href', $block->data->link);
        $link_title->appendChild($this->html5->loadHTMLFragment($block->data->meta->title));

        $link->appendChild($link_title);

        $figure->appendChild($link);

        $this->dom->appendChild($figure);
    }
}
