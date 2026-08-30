<?php

namespace App\Support;

use Fpdf\Fpdf;

class LegacyPdfHtml extends Fpdf
{
    public int $B = 0;
    public int $I = 0;
    public int $U = 0;
    public string $HREF = '';
    public string $ALIGN = '';

    public function WriteHTML(string $html, string $font = '', float $lineHeight = 1): void
    {
        if (preg_match('/^\s*<center(?:\s[^>]*)?>(.*)<\/center>\s*$/is', $html, $centered) === 1) {
            $this->WriteCenteredHTML($centered[1], $font, $lineHeight);
            return;
        }

        $parts = preg_split('/<(.*)>/U', str_replace("\n", ' ', $html), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                if ($this->HREF !== '') $this->PutLink($this->HREF, $part, $font, $lineHeight);
                elseif ($this->ALIGN === 'center') $this->Cell(0, 5 * $lineHeight, $part, 0, 1, 'C');
                else $this->Write(5 * $lineHeight, $part);
                continue;
            }
            if (str_starts_with($part, '/')) $this->CloseTag(strtoupper(substr($part, 1)), $font);
            else {
                $chunks = explode(' ', $part); $tag = strtoupper((string) array_shift($chunks)); $properties = [];
                foreach ($chunks as $chunk) if (preg_match('/([^=]*)=["\']?([^"\']*)/', $chunk, $matches)) $properties[strtoupper($matches[1])] = $matches[2];
                $this->OpenTag($tag, $properties, $font);
            }
        }
    }

    private function WriteCenteredHTML(string $html, string $font, float $lineHeight): void
    {
        $parts = preg_split('/<(.*)>/U', str_replace("\n", ' ', $html), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $startX = $this->GetX();
        $lineWidth = max($this->GetPageWidth() - $this->rMargin - $startX, 1);
        $height = 5 * $lineHeight;
        $line = [];
        $usedWidth = 0.0;
        $pendingSpace = false;

        $flush = function () use (&$line, &$usedWidth, $startX, $lineWidth, $height, $font): void {
            if ($line === []) return;
            $this->SetX($startX + max(($lineWidth - $usedWidth) / 2, 0));
            foreach ($line as $segment) {
                $this->SetFont($font, $segment['style']);
                $this->Cell($segment['width'], $height, $segment['text'], 0, 0);
            }
            $this->Ln($height);
            $line = [];
            $usedWidth = 0.0;
        };

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $tag = strtoupper(trim($part));
                $closing = str_starts_with($tag, '/');
                $tag = ltrim($tag, '/');
                if (in_array($tag, ['B', 'I', 'U'], true)) $this->SetStyle($tag, ! $closing, $font);
                if ($tag === 'BR') $flush();
                continue;
            }

            foreach (preg_split('/(\s+)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $token) {
                if ($token === '') continue;
                if (preg_match('/^\s+$/', $token)) { $pendingSpace = true; continue; }
                $style = $this->currentStyle();
                $this->SetFont($font, $style);
                $text = ($pendingSpace && $line !== [] ? ' ' : '').$token;
                $pendingSpace = false;
                $width = $this->GetStringWidth($text);
                if ($line !== [] && $usedWidth + $width > $lineWidth) {
                    $flush();
                    $this->SetFont($font, $style);
                    $text = ltrim($text);
                    $width = $this->GetStringWidth($text);
                }
                $line[] = ['text' => $text, 'width' => $width, 'style' => $style];
                $usedWidth += $width;
            }
        }
        $flush();
        $this->SetFont($font, '');
    }

    private function currentStyle(): string
    {
        $style = '';
        foreach (['B', 'I', 'U'] as $candidate) if ($this->{$candidate} > 0) $style .= $candidate;
        return $style;
    }

    public function OpenTag(string $tag, array $properties, string $font = ''): void
    {
        if (in_array($tag, ['B', 'I', 'U'], true)) $this->SetStyle($tag, true, $font);
        if ($tag === 'A') $this->HREF = $properties['HREF'] ?? '';
        if ($tag === 'BR') $this->Ln(5);
        if ($tag === 'P' || $tag === 'CENTER') $this->ALIGN = strtolower($properties['ALIGN'] ?? ($tag === 'CENTER' ? 'center' : ''));
        if ($tag === 'HR') {
            $width = ! empty($properties['WIDTH']) ? (float) $properties['WIDTH'] : $this->GetPageWidth() - $this->lMargin - $this->rMargin;
            $this->Ln(2); $x = $this->GetX(); $y = $this->GetY(); $this->SetLineWidth(.4); $this->Line($x, $y, $x + $width, $y); $this->SetLineWidth(.2); $this->Ln(2);
        }
    }

    public function CloseTag(string $tag, string $font = ''): void
    {
        if (in_array($tag, ['B', 'I', 'U'], true)) $this->SetStyle($tag, false, $font);
        if ($tag === 'A') $this->HREF = '';
        if ($tag === 'P' || $tag === 'CENTER') $this->ALIGN = '';
    }

    public function SetStyle(string $tag, bool $enabled, string $font = ''): void
    {
        $this->{$tag} += $enabled ? 1 : -1;
        $style = '';
        foreach (['B', 'I', 'U'] as $candidate) if ($this->{$candidate} > 0) $style .= $candidate;
        $this->SetFont($font, $style);
    }

    public function PutLink(string $url, string $text, string $font = '', float $lineHeight = 1): void
    {
        $this->SetTextColor(0, 0, 255); $this->SetStyle('U', true, $font); $this->Write(5 * $lineHeight, $text, $url); $this->SetStyle('U', false, $font); $this->SetTextColor(0);
    }
}
