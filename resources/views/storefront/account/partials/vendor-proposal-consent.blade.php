@if(session('vendor_proposal_decision_saved'))
    <p class="sf-account-panel" role="status">{{ __('booking::booking.intervention.success.proposal_decision_saved') }}</p>
@endif

@if(($pendingVendorProposals ?? collect())->isNotEmpty())
    <section class="sf-account-panel" aria-labelledby="vendor-proposals-title">
        <h2 id="vendor-proposals-title">{{ __('booking::booking.intervention.proposal_title') }}</h2>
        <p>{{ __('booking::booking.intervention.proposal_intro') }}</p>

        @foreach($pendingVendorProposals as $proposal)
            @php($offer = (array) data_get($proposal->after_state, 'offer', []))
            <article class="sf-account-panel" aria-labelledby="vendor-proposal-{{ $proposal->public_id }}">
                <h3 id="vendor-proposal-{{ $proposal->public_id }}">
                    {{ data_get($offer, 'candidate_vendor.business_name.'.app()->getLocale()) ?: data_get($offer, 'candidate_vendor.business_name.en') }}
                </h3>
                <p>{{ __('booking::booking.intervention.proposal_deadline') }}: <bdi>{{ data_get($offer, 'response_deadline') }}</bdi></p>
                <ul>
                    @foreach((array) data_get($offer, 'items', []) as $offeredItem)
                        <li>
                            {{ data_get($offeredItem, 'replacement_service.name.'.app()->getLocale()) ?: data_get($offeredItem, 'replacement_service.name.en') }}
                            · {{ __('booking::booking.intervention.proposal_quantity') }}: {{ data_get($offeredItem, 'quantity') }}
                        </li>
                    @endforeach
                </ul>
                <p>{{ __('booking::booking.intervention.proposal_price_note') }}</p>
                <form method="post" action="{{ route('storefront.account.bookings.vendor-proposals.decide', ['bookingPublicId' => $booking->public_id, 'interventionPublicId' => $proposal->public_id]) }}">
                    @csrf
                    <input type="hidden" name="offer_hash" value="{{ data_get($offer, 'offer_hash') }}">
                    <label for="proposal-reason-{{ $proposal->public_id }}">{{ __('booking::booking.intervention.proposal_reason_optional') }}</label>
                    <textarea id="proposal-reason-{{ $proposal->public_id }}" name="reason" maxlength="500"></textarea>
                    <button class="sf-button sf-button--primary" type="submit" name="decision" value="accepted">{{ __('booking::booking.intervention.proposal_accept') }}</button>
                    <button class="sf-button" type="submit" name="decision" value="rejected">{{ __('booking::booking.intervention.proposal_reject') }}</button>
                </form>
            </article>
        @endforeach
    </section>
@endif
