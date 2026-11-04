<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * ArifPay's page numbers start at 0.
 */
trait Paginates
{
    public function getPage(): ?int
    {
        return $this->getParameter('page');
    }

    /**
     * @return $this
     */
    public function setPage(?int $value): self
    {
        return $this->setParameter('page', $value);
    }

    public function getSize(): ?int
    {
        return $this->getParameter('size');
    }

    /**
     * @return $this
     */
    public function setSize(?int $value): self
    {
        return $this->setParameter('size', $value);
    }

    protected function pageQuery(array $filters = []): string
    {
        // http_build_query drops nulls, which leaves ArifPay's own defaults.
        $query = http_build_query($filters + ['page' => $this->getPage(), 'size' => $this->getSize()]);

        return $query === '' ? '' : '?' . $query;
    }
}
