<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA;
 */

namespace oat\tao\scripts\tools\symfonyMessenger;

class ExampleTask implements \JsonSerializable
{
    /**
     * @var string
     */
    private $p1;

    /**
     * @param string $p1
     */
    public function __construct(string $p1)
    {
        $this->p1 = $p1;
    }

    /**
     * @return string
     */
    public function getP1(): string
    {
        return $this->p1;
    }

    public function jsonSerialize()
    {
        return [
            'p1' => $this->getP1()
        ];
    }
}