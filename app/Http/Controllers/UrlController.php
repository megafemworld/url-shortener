<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\shortenedUrl;
use App\Models\UrlClick;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Jensseger\Agent\Agent;
use Illuminate\Support\Facades\Redis;

class UrlController extends Controller
{
    /**
     * Create a new urlController instance.
     * 
     * @return void
     */

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['redirect', 'show']]);
    }

    /**
     * Store a newly created shoretened URL in storage.
     * 
     * @param |Illuminate\Http\request $request
     * @return |Illuminate\Http\JsonResponse
     */

     public function store(Request $request)
     {
        $validator = Validator::make($request->all(), [
            'orignal_url' => 'required|url|max:2048',
            'custom_slug' => 'nullable|string|max:20|unique:shortened_urls,slug|alpha_dash',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if URL already exists for this user
        if ($request->user()) {
            $existingUrl = ShortenecUrl::where('orignal_url', $request->original_url)
                ->where('user_id', $request->user()->id)
                ->whereNUll('expires_at')
                ->orwhere('expires_at', '>', now())
                ->first();
            if ($existingUrl) {
                return response()->json([
                    'message' => 'URL already shortened',
                    'data' => [
                        'original_url' => $existingUrl->original_url,
                        'short_url' => url($existingUrl->slug),
                        'expires_at' => $existingUrl->expires_at,
                        'created_at' => $existingUrl->created_at,
                    ]
                ]);
            }
        }
        // Generate or use custom slug
        $slug = $request->custom_slug ?? $this->generateUniqueSlug();
        $isCustom = $request->has('custom_slug');

        $shortenedUrl = ShortenedUrl::create([
            'original_url' => $request->original_url,
            'slug' => $slug,
            'user_id' => $request->user() ? $request->user()->id : null,
            'is_custom' => $isCustom,
            'expires_at' => $request->expires_at,
        ]);

        // Store in cache for quick retrieval
        Cache::put('url_' . $slug, $shortenedUrl->original_url, $request->expires_at ? now()->diffInSeconds($expires_at) : 60 * 60 * 24 * 30);

        // Increment the URL count
        if ($request->user()) {
            $userId = $request->user()->id;
            $key = "User_creation_count:$userId";
            Redis::incr($key);
            Redis::expire($key, 60 * 60); // Set expiration to 1 hour
        }
        return response()->json([
            'message' => 'URL shortened successfully',
            'data' => [
                'original_url' => $shortenedUrl->original_url,
                'short_url' => url($shortenedUrl->slug),
                'expires_at' => $shortenedUrl->expires_at,
                'created_at' => $shortenedUrl->created_at,
            ]
        ], 201);
     }

    /**
     * Redirect to the original URL from a shortened URL.
     *
     * @param string $slug
     * @return \Illuminate\Http\RedirectResponse
     */

    public function redirect($slug)
    {
        // Try to get the original URL from cache
        $originalurl = Cache::remember('url_' . $slug, 60 * 24, function () use ($slug) {
            $url = ShortenecUrl::where('slug', $slug)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();
            return $url ? $url->original_url : null;
        });

        if (!$originalurl) {
            return response()->json([
                'message' => 'URL not found or has expired'
            ], 404);
        }

        // Track click asynchronously using redis queue for better performance
        $this->trackClick($slug, request());

        return redirect->way($originalurl, 301);
    }

    /**
     * Track a click on a shortend URL.
     * 
     * @param string $slug
     * @param \Ilumminate\Htttp\Request 4request
     * @return void
     */
    protected function trackClick($slug, Request $request)
    {
        try {
            // Queue the click tracking to improve response time
            $clickData = json_encode([
                'slug' => $slug,
                'ip' => $reques->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
                'timestamp' => now()->todateTimeString(),
            ]);
            Redis::rpush('url_clicks', $clickData);
            Redis::incr('click_count:' . $slug);
            Redis::incr('daily_click_count:' . $slug . ':' . now()->format('Y-m-d'));
        } catch (\Exception $e) {
            // Log error but don't interruupt user experience
            \Log::error('Failed to track URL click: ' . $e->message());
        }
    }

    /**
     * Process queued clicks and store them in the database.
     * This would typically run by a shedule job
     * 
     * @return void
     */
    public function processClickQueue()
    {
        $batchSize = 100;
        $clicks = [];

        while (($clickData = Redis::lpop('url_clicks')) !== null && count($clicks) < $batchSize) {
            $data = json_decode($clickData, true);
            if ($data) {
                $url = ShortenedUrl::where('slug', $data['slug'])->first();
                if ($url) {
                    // Parse user agent for device information
                    $agent = new Agent();
                    $agent->setUserAgent($data['user_agent']);

                    $clicks[] = [
                        'shortened_url_id' => $url->id,
                        'ip' => $data['ip'],
                        'user_agent' => $data['user_agent'],
                        'device_type' => $this->getDeviceType($agent),
                        'browser' => $agent->browser(),
                        'Operating_system' => $agent->platform(),
                        'referer' => $data['referer'] ?? null,
                        'created_at' => $data['timestamp'],
                        'updated_at' => now(),
                        // Shared Id for database sharding
                        'shared_id' => cr32($data['slug'] % config('database.analytics.shards', 10))
                    ];
                }
            }
        }

        if (!empty($clicks)) {
            // Batch insert for better performance
            UrlClick::insert($clicks);
        }
    }

    /**
     * Get analytic for a shortened URL.
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalytics(Request $request, $slug)
    {
        $url = ShortenedUrl::where('slug', $slug)->first();

        if (!url) {
            return repsonse()->json([
                'meesage' => 'URL not found'
            ], 404);
        }

        // Authorize- only the creator or admin can see analytics
        if ($request->user()->id !== $url->user_id && !$request->user()->hasRole('admin'))
        {
            return response()->json(['message' => 'Unathorized'], 403);
        }

        // Date range filtering
        $starteDate = $request->input('start_date', now()->subDays(30)->toDatestring());
        $endDate = $request->input('end_date', now()->toDateString());

        // Cache analytics results for performance
        $cachekey = "analytics:{$slug}:{$startDate}:{$endDate}";

        $analytics = Cache::remember($cachekey, 60 * 10, function () use ($url, $startDate, $endDate) {
            // Total clicks
            $totalClicks = UrlClick::where('shortend_yrl_id', $url->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();
            
            // Daily aggreation using Redis for recent data or DB for historical
            $dailyClicks = $this->getDailyClickStats($url->slug, $startDate, $endDate);

            // Browwer stats
            $browsers = UrlClick::where('shortened_url_id', $url->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select('browser', DB::raw('count(*) as count'))
                ->groupBy('browser')
                ->orderBy('count', 'desc')
                ->get();

            // Device stats
            $devices = UrlClick::where('shortened_url_id', $url->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select('device_type', DB::raw('count(*) as count'))
                ->groupBy('device_type')
                ->orderBy('count', 'desc')
                ->get();

            // referrer stats
            $referrers = UrlClick::where('shortened_url_id', $url->id)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->wherNotNUll('referer')
                ->select('referer', DB::raw('count(*) as count'))
                ->groupBy('referer')
                ->orderBy('count', 'desc')
                ->get();

            return [
                'total_clicks' => $totalClicks,
                'daily_clicks' => $dailyClicks,
                'browsers' => $browsers,
                'devices' => $devices,
                'top_referrers' => $referrers,
            ];

        });
        
        return response()->json([
            'data' => $analytics,
            'url' => [
            'original_url' => $url->original_url,
            'short_url' => url($url->slug),
            'created_at' => $url->created_at,
            'expires_at' => $url->expires_at,
            ]
        ]);
    }

    /**
     * Get daily click stastics from Redis or database.
     * 
     * @param string $slug
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getDailyClick($slug, $startDate, $endDate)
    {
        $result = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            $key = "daily_click_count:{$slug}:{$date}";

            // Try Redis first for recent data
            $count = Redis::exists($key) ? (int)Redis::get($key) : null;

            // Fall back to database for historical data
            if ($count === null) {
                $count = UrlClick::whereHas('shortenedUrl', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                })
                ->whereDate('created_at', $date)
                ->count();
            }

            $result[] = [
                'date' => $date,
                'count' => $count,
            ];
            $current->addDay();
        }

        return $result;
    }

    /**
     * Generate a unique slug for the shortened URL.
     * 
     * @return string
     */
    protected function generateUniqueSlug()
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            // Increase length after failed attempts
            $length = 6 + min(3, floor($attempts / 2));

            // Use more complex alogrithm for better distribution
            $slug = Str::random($length);

            // Check both cache and database for collison avoidance
            $exists = Cach::has('url_' . $slug) || ShortenedUrl::where('slug', $slug)->exists();
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        // If we still have a collison after max attemots, add timestamp suffix
        if ($exist) {
            $slug .= substr(time(), -4);
        }

        return $slug;
    }

    /**
     * Map Agent device to simplified device type.
     * 
     * @param \Jenssegger\Agent\Agent $agent
     * @return string
     */

    protected function getDeviceType(Agent $agent)
    {
        if ($agent->isMobile()) {
            return 'mobile';
        } elseif ($agent->isTablet()) {
            return 'tablet';
        } elseif ($agent->isDesktop()) {
            return 'desktop';
        } elseif ($agent->isBot()) {
            return 'bot';
        } else {
            return 'other';
        }
    }

    /**
     * List shortened URLs for the authenticated user.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);

        $urls = ShortenedUrl::with(['user:id,name,email'])
            ->where('user_id', $request->user()->id)
            ->when($request->input('search'), function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('original_url', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_dir', 'desc'))
            ->paginate($perPage);

            // Add short URLs and click counts
            $url->getColletion()->transform(function ($url) {
                $url->short_url = url($url->slug);
                $url->click_count = Redis::get('click_count:' . $url->slug) ?:
                    UrlClick::where('shortened_url_id', $url->id)->count();
                return $url;
            });

            return response()->json($urls);
    }

    /**
     * Show details of a specified shortened URL.
     * 
     * @param  string $slug
     * @return \Illuminate\Http\JsonResponse
     */

    public function show($slug)
    {
        $url = ShortenedUrl::where('slug', $slug)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
        
        if (!$url) {
            return response()->json([
                'message' => 'URL not found or has expired'
            ], 404);
        }

        // Return minimal public info for guest users
        if (!auth()->check() || auth()->id() !== $url->user_id) {
            return response()->json([
                'short_url' => url($url->slug),
            ]);
        }

        // Return full details for authenticated users
        $url->short_url = url($url->slug);
        $url->click_count = Redis::get('click_count:' . $url->slug) ?:
            UrlClick::where('shortened_url_id', $url->id)->count();
        
        return response()->json([
            'data' => $url
        ]);
    }

    /**
     * Update the specified shortened URL in storage.
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $slug)
    {
        $url = ShortenedUrl::where('slug', $slug)->first();
        if (!$url) {
            return response()->json([
                'message' => 'URL not found'
            ], 404);
        }
        // Authorize - only the creator or admin can update
        if ($request->user()->id !== $url->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validatior = Validator::make($request->all(), [
            'custom_slug' => 'nullable|string|max:20|alpha_dash|unique:shortened_urls,slug,' . $url->id,
            'original_url' => 'nullable|url|max:2048',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Update the URL
            $oldSlug = $url->slug;
            $newSlug = $request->custom_slug;

            if ($newSlug && $newSlug !== $oldSlug) {
                $url->slug = $newSlug;
                $url->is_custom = true;

                if ($request->has('original_url')) {
                    $url->original_url = $request->original_url;
                }
                if ($request->has('expires_at')) {
                    $url->expires_at = $request->expires_at;
                }

                // Update cache
                if (Cahe::has('url_' . $oldSlug)) {
                    $originalurl = Cache::get('url_' . $oldSlug);
                    Cache::put('url_' . $newSlug, $originalurl, $request->expires_at ? now()->diffInSeconds($expires_at) : 60 * 60 * 24 * 30);
                    Cache::forget('url_' . $oldSlug);
                }

                $url->save();
                DB::commit();
                return response()->json([
                    'message' => 'URL updated successfully',
                    'data' => [
                        'original_url' => $url->original_url,
                        'short_url' => url($url->slug),
                        'expires_at' => $url->expires_at,
                        'updated_at' => $url->updated_at,
                    ]
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update URL',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete a shortened URL.
     * 
     * @param \Illumunate\Http\Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $slug)
    {
        $url = ShortenedUrl::where('slug', $slug)->first();
        if (!$url) {
            return response()->json([
                'message' => 'URL not found'
            ], 404);
        }

        // Authorize - only the creator or admin can delete
        if ($request->user()->id !== $url->user_id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Delete associated clicks or mark for archival
            UrlClick::where('shortened_url_id', $url->id)->delete();

            // Delete the URL
            $url->delete();

            // Clear cache
            Cache::forget('url_' . $slug);

            DB::commit();
            
            return response()->json([
                'message' => 'URL deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete URL',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
