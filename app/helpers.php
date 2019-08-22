<?php

use Illuminate\Support\Facades\Cache;
use Cake\Chronos\Chronos;
use Illuminate\Support\Str;


/**
 * @return Cake\Chronos\Chronos
 */
function as_date(...$args)
{
    if (is_int($args[0])) {
        return Chronos::createFromTimestamp($args[0]);
    }
    return new Chronos(...$args);
}

function log_error($e)
{
    logger()->error($e->getMessage(), [
        'file' => get_error_location($e),
    ]);
}

function get_plans()
{
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    try {
        return Cache::remember(
            'stripe.plans:' . config('services.stripe.product'),
            now()->addHours(24),
            function () {
                $plans = \Stripe\Plan::all([
                    "limit" => 3,
                    "product" => config('services.stripe.product'),
                ]);

                return $plans->data;
            }
        );
    } catch (\Exception $e) {
        logger()->error($e->getMessage());
        return [];
    }
}

function get_integration($key, $service)
{
    if (!$key) {
        return false;
    }
    return Cache::remember(
        'integration.' . $service . '.' . $key,
        now()->addHours(24),
        function () use ($key, $service) {
            return \App\Integration::where('remote_id', $key)
                ->where('service', $service)
                ->first();
        }
    );
}

// date_parse_fixed fixes date_parse so we can detect dates without day
function date_parse_fixed($dateRaw)
{
    $dateRaw = trim($dateRaw);
    if (strlen($dateRaw) === 4 && preg_match("/\d{4}/", $dateRaw) === 1) {
        $da = date_parse($dateRaw . "-01-01");
        $da["month"] = false;
        $da["day"] = false;
        return $da;
    }
    $da = date_parse($dateRaw);
    if (!$da) {
        return [];
    }
    if (
        array_key_exists("year", $da) &&
        array_key_exists("month", $da) &&
        array_key_exists("day", $da)
    ) {
        if ($da["day"] === 1) {
            if (preg_match("/\b0?1(?:\b|T)/", $dateRaw) !== 1) {
                $da["day"] = false;
            }
        }
    }
    return $da;
}

function check_is_date($date)
{
    return !empty($date['year']) && !empty($date['month']);
}

function to_float($num)
{
    $num = str_replace(' ', '', $num);
    $dotPos = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep = (($dotPos > $commaPos) && $dotPos)
        ? $dotPos
        : ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }

    return floatval(
        preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) .
            '.' .
            preg_replace("/[^0-9]/", "", substr($num, $sep + 1, strlen($num)))
    );
}

function normalize_record($record_data, $project)
{
    $record = [
        'name' => $record_data['name'],
        'type' => $record_data['type'],
        'category_code' => $record_data['category'],
        'planned' => $record_data['planned'] ?? true,
        'direct' => $record_data['direct'] ?? true,
    ];
    debug('record_data', $record_data);
    $autofill = null;

    if (array_has($record_data, 'auto') && $record_data['auto']) {
        $autofill = array_pull($record_data, 'period', []);
        $autofill = array_merge($autofill, array_pull($record_data, 'cost', []));

        // might not have quantity or price in labor records
        $autofill['quantity'] =
            $autofill['quantity'] ?? abs(array_get($record_data, 'cost.quantity', 1)) ?: 1;
        $autofill['price'] =
            $autofill['price'] ?? abs(array_get($record_data, 'cost.price', 0))
            ?: abs(array_get($record_data, 'price', 0));
        debug('autofill', $autofill);
    } elseif (array_has($record_data, 'autofill')) {
        $autofill = array_pull($record_data, 'autofill');
        array_pull($record_data, 'cost');
    }

    array_pull($record_data, 'period');
    array_pull($record_data, 'daily');
    array_pull($record_data, 'monthly');
    array_pull($record_data, 'price');
    array_pull($record_data, 'quantity');
    array_pull($record_data, 'date');
    $contact = array_pull($record_data, 'contact');
    $product = array_pull($record_data, 'product');

    unset($record_data['direct'],
    $record_data['expense'],
    $record_data['planned'],
    $record_data['type'],
    $record_data['auto'],
    $record_data['name'],
    $record_data['category']);

    $record['meta'] = $record_data;
    $record['autofill'] = $autofill;
    $record['contact'] = $contact;
    $record['product'] = $product;
    return $record;
}

function ok()
{
    return response('', 204);
}

/**
 * @return \App\User|\App\Customer|null
 */
function user()
{
    return auth()->user();
}

/**
 * @return \App\User
 */
function context()
{
    $id = user()->context_id ?: auth()->id();
    return Cache::remember('context.id:' . $id, now()->addHours(24), function () use ($id) {
        return \App\User::where('id', $id)->first();
    });
}

function str_match($string, $pattern)
{
    preg_match($pattern, $string, $matches);
    return $matches[1] ?? false;
}

function is_uuid($string)
{
    return !!preg_match(
        '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
        $string
    );
}

function get_error_location($e)
{
    $trace = current($e->getTrace());
    $file = array_get($trace, 'file');
    $line = array_get($trace, 'line');
    if ($file && $line) {
        return $file . ':' . $line;
    }
    $file = array_get($trace, 'args');
    if (is_string($file)) {
        return $file;
    }
    $line = isset($file[3]) ? $file[3] : '';
    $file = isset($file[2]) ? $file[2] : 'unknown';
    return $file . ':' . $line;
}

function str_slug_u($str)
{
    return str_slug(transliterator_transliterate('Any-Latin', $str));
}

function humanize($value)
{
    if (is_object($value)) {
        return humanize(class_basename(get_class($value)));
    }

    return Str::title(Str::snake($value, ' '));
}
if (!function_exists('debug')) {
    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    function debug($message, $context)
    {
        logger()->debug($message, $context);
    }
}

/**
 * @return string
 */
function make_UUID()
{
    return strtolower(str_replace('-', '', Str::uuid()->toString()));
}
