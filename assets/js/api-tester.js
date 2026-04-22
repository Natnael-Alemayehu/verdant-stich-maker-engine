/**
 * Verdant Stitch - Admin API Tester
 * 
 * Sends REST requests directly from the WordPress admin page
 * using the current logged-in user's nonce (cookie auth).
 */
( function ( $ ){
    'use strict';

    const base  = verdantAdmin.apiBase;
    const headers = {
        'Content-Type' : 'application/json',
        'X-WP-Nonce'   : verdantAdmin.nonce,
    };

    /**
     * Show a formatted JSON response in the response box.
     */
    function showResponse( id, data, isError = false ) {
        const $box = $( '#resp-' + id );
        $box.removeClass( 'loading error' )
            .addClass('visible' + (isError ? ' error' : ''))
            .text(JSON.stringify( data,null, 2 ));
    }

    function showLoading( id ) {
        const $box = $('#resp-' + id);
        $box.addClass( 'visible loading' ).text( 'Sending request...' );
    }

    /**
     * Generic fetch wrapper.
     */
    async function apiFetch(id, method, url, body = null) {
        showLoading(id);
        try {
            const opts = { method, headers };
            if (body) {
                opts.body = JSON.stringify(body);
            }
            const res = await fetch( url, opts );
            const data = await res.json();
            showResponse(id, data, ! res.ok );
        } catch (err) {
            showResponse(id, {error: err.message}, true);
        }
    }

    // Button handlers

    $(document ).on('click', 'verdant-run-btn', async function(){
        const endpoint = $(this).data('endpoint');

        switch (endpoint) {
            case 'get-profile': {
                const uid = $('#gp-user-id').val();
                const url = uid ? `${base}/progress?user_id=${ uid }` : `${ base }/progress`;
                await apiFetch('get-profile', 'GET', url);
                break;
            }
            case 'create-kit': {
                const body = {
                    kit_id:     $( '#ck-kit-id' ).val(),
                    kit_name:   $( '#ck-kit-name' ).val(),
                    difficulty: parseInt( $('#ck-difficulty').val(), 10),
                    total_steps: parseInt( $('#ck-total-steps').val(), 10),
                };
                await apiFetch( 'create-kit', 'POST', `${base}/progress/kit`, body );
                break;
            }
            case 'update-steps': {
                const kitId = $( '#us-kit-id' ).val();
                const body = {
                    completed_steps: parseInt( $('#us-steps').val(), 10),
                    note: $('#us-note').val(),
                };
                await apiFetch( 'update-steps', 'POST', `${base}/progress/${kitId}/steps`, body );
                break;
            }
            case 'add-image': {
                const kitId = $( '#ai-kit-id' ).val();
                const body = {
                    image_url: $( '#ai-url' ).val(),
                    step_number: parseInt( $('#ai-step').val(), 10 ),
                    caption: $( '#ai-caption' ).val(),
                };
                await apiFetch('add-image', 'POST', `${base}/progress/${kitId}/images`, body);
                break;
            }
            case 'get-mastery': 
                await apiFetch('get-mastery', 'GET', `${base}/mastery`);
                break;
            case 'recalculate':
                await apiFetch( 'recalculate', 'POST', `${base}/mastery/recalculate` );
                break;
            case 'get-levels':
                await apiFetch('get-levels', 'GET', `${base}/levels`);
                break;
        }
    });
})( jQuery );