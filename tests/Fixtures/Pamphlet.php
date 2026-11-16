<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\CustomFields\Tests\Fixtures;

use Atrium\Admin\Contracts\Administrable;

/**
 * Says it is an admin module, but it is no model: nothing to add fields to.
 */
abstract class Pamphlet implements Administrable {}
