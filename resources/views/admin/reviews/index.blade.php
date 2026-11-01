<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Feedback') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('admin.reviews.index') }}" class="bg-white p-4 shadow-sm sm:rounded-lg grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div>
                    <x-input-label for="rating" :value="__('Rating')" />
                    <select id="rating" name="rating" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('Any rating') }}</option>
                        @foreach ($ratings as $rating)
                            <option value="{{ $rating }}" @selected((string) ($filters['rating'] ?? '') === (string) $rating)>{{ trans_choice(':count star|:count stars', $rating) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('rating')" />
                </div>
                <div>
                    <x-input-label for="technician" :value="__('Technician')" />
                    <select id="technician" name="technician" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="">{{ __('Any technician') }}</option>
                        @foreach ($technicians as $technician)
                            <option value="{{ $technician->id }}" @selected((string) ($filters['technician'] ?? '') === (string) $technician->id)>{{ $technician->name }}@unless ($technician->is_active) ({{ __('deactivated') }})@endunless</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('technician')" />
                </div>
                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    @if (array_filter($filters))
                        <a href="{{ route('admin.reviews.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                    @endif
                </div>
                <div class="text-sm text-gray-700 lg:text-right">
                    @if ($reviews->total() > 0)
                        {{ __('Average :average / :max from :count', ['average' => number_format($average, 1), 'max' => App\Models\Review::MAX_RATING, 'count' => trans_choice(':count review|:count reviews', $reviews->total())]) }}
                    @endif
                </div>
            </form>

            @if ($reviews->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    @if (array_filter($filters))
                        <p class="text-gray-700">{{ __('No reviews match these filters.') }}</p>
                        <a href="{{ route('admin.reviews.index') }}" class="mt-2 inline-block text-indigo-600 hover:text-indigo-900">{{ __('Show all feedback') }}</a>
                    @else
                        <p class="text-gray-700">{{ __('No reviews have been submitted yet.') }}</p>
                    @endif
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Ticket') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Rating') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Comment') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Requester') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Technician') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Submitted') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($reviews as $review)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.tickets.show', $review->ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $review->ticket->reference }}</a>
                                        <div class="text-gray-700">{{ $review->ticket->title }}</div>
                                    </td>
                                    <td class="px-4 py-3">@include('tickets.partials.rating', ['rating' => $review->rating])</td>
                                    <td class="px-4 py-3 text-gray-900 whitespace-pre-line">{{ $review->comment ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $review->user->name }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $review->ticket->technician?->name ?? __('Not assigned') }}</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $review->created_at->format('d M Y, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $reviews->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
