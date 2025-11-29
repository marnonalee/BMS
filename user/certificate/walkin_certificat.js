$(document).ready(function(){

    $('#toggleSidebar').click(function(){
        $('#sidebar').toggleClass('sidebar-collapsed');
        let icon = $(this).text();
        $(this).text(icon === 'chevron_left' ? 'chevron_right' : 'chevron_left');
    });

    function fetchResidents(query=''){
        $.ajax({
            url: 'walkin_certificates.php',
            type: 'GET',
            data: { search: query },
            dataType: 'json',
            success: function(results){
                let dropdown = $('#resident_dropdown');
                dropdown.empty();
                if(results.length > 0){
                    results.forEach(res => {
                        dropdown.append(`
                            <div class="px-2 py-1 hover:bg-gray-100 cursor-pointer" 
                                data-id="${res.resident_id}" 
                                data-name="${res.first_name} ${res.last_name}" 
                                data-age="${res.age}" 
                                data-status="${res.civil_status}" 
                                data-address="${res.resident_address}"
                                data-birth_place="${res.birth_place}"
                                data-birthdate="${res.birthdate}"
                                data-sex="${res.sex}"
                                data-voter_status="${res.voter_status}"
                                data-profession="${res.profession_occupation}">
                                ${res.first_name} ${res.last_name}
                            </div>
                        `);
                    });
                } else {
                    dropdown.append('<div class="px-2 py-1 text-gray-500">No resident found</div>');
                }
            }
        });
    }

    $('#resident_search').on('focus input', function(){
        fetchResidents($(this).val());
    });

    $(document).on('click','#resident_dropdown div', function(){
        let id = $(this).data('id');
        let name = $(this).data('name');
        let age = $(this).data('age');
        let status = $(this).data('status');
        let address = $(this).data('address');
        let birth_place = $(this).data('birth_place');
        let birthdate = $(this).data('birthdate');
        let sex = $(this).data('sex');
        let voter_status = $(this).data('voter_status');
        let profession = $(this).data('profession');

        $('#resident_search').val(name);
        $('#resident_id').val(id);
        $('#resident_fullname').val(name);
        $('#resident_age').val(age);
        $('#resident_status').val(status);
        $('#resident_address').val(address);
        $('#resident_birthplace').val(birth_place);
        $('#resident_birthdate').val(birthdate);
        $('#resident_sex').val(sex);
        $('#resident_voter_status').val(voter_status);
        $('#resident_profession').val(profession);

        $('#resident_card').removeClass('hidden');
        $('#card_name').text(name);
        $('#card_age_status').text(`${age} years old • ${status}`);
        $('#card_address').text(address);
        $('#resident_dropdown').empty();
    });

    const templateSelect = document.getElementById('template_id');
    const guardianSection = document.getElementById('guardianSection');
    const earningsDiv = document.getElementById('earningsDiv');
    const purposeDiv = document.getElementById('purpose').parentElement;

    templateSelect.addEventListener('change', () => {
        const selectedText = templateSelect.options[templateSelect.selectedIndex].text.trim().toLowerCase();

        if(selectedText === "certificate of attestation") {
            earningsDiv.classList.remove('hidden'); 
             $('#professionDiv').removeClass('hidden');
        } else {
            earningsDiv.classList.add('hidden'); 
            $('#earnings_per_month').val('');
            $('#professionDiv').addClass('hidden');
        }

        if(selectedText === "certificate of guardianship") {
            guardianSection.classList.remove('hidden');
            $('#purpose').val('For guardianship purposes');
        } else {
            guardianSection.classList.add('hidden');
            $('#child_id,#child_fullname,#child_age,#child_birthdate,#child_birthplace').val('');
            $('#child_card').hide();
            $('#child_manual').show();
            if(selectedText !== "barangay certification") {
                $('#purpose').val('');
            }
        }

        purposeDiv.style.display = (selectedText === "certificate of attestation") ? 'none' : 'block';
    });

    $('#generateCertificate').click(function(){
        let residentId = $('#resident_id').val();
        let templateId = $('#template_id').val();
        let purpose = $('#purpose').val().trim();
        let earnings = $('#earnings_per_month').val().trim();
        let profession = $('#resident_profession').val().trim();
        let childId = $('#child_id').val().trim();
        let selectedText = templateSelect.options[templateSelect.selectedIndex].text.trim().toLowerCase();

        let fullname = $('#resident_fullname').val().trim();
        let age = $('#resident_age').val().trim();
        let civilStatus = $('#resident_status').val().trim();
        let address = $('#resident_address').val().trim();
        let birthplace = $('#resident_birthplace').val().trim();
        let birthdate = $('#resident_birthdate').val().trim();
        let sex = $('#resident_sex').val().trim();
        let voterStatus = $('#resident_voter_status').val().trim();

        let childFullname = $('#child_fullname').val().trim();
        let childAge = $('#child_age').val().trim();
        let childBirthdate = $('#child_birthdate').val().trim();
        let childBirthplace = $('#child_birthplace').val().trim();

        if(!residentId){ showMessageModal('Please select a resident.','error'); return; }
        if(!templateId){ showMessageModal('Please select a certificate type.','error'); return; }

        if(selectedText === 'barangay certification'){
            if(!fullname || !age || !civilStatus || !address || !birthplace || !birthdate || !sex || !voterStatus){
                showMessageModal('Please fill all required fields for Barangay Certification.','error');
                return;
            }
            if(!purpose){
                showMessageModal('Purpose is required for Barangay Certification.','error');
                return;
            }
        }

        if(selectedText === 'certificate of attestation'){
            if(!fullname || !age || !civilStatus || !address || !birthplace || !birthdate || !sex || !voterStatus){
                showMessageModal('Please fill all required fields for Certificate of Attestation.','error');
                return;
            }
            if(!earnings){
                showMessageModal('Earnings per month is required for Certificate of Attestation.','error');
                return;
            }
            if(!profession){
                showMessageModal('Profession/Occupation is required for Certificate of Attestation.','error');
                return;
            }
        }

        if(selectedText === 'certificate of guardianship'){
            if(!childFullname || !childAge || !childBirthdate || !childBirthplace){
                showMessageModal('Please complete all required child information: Full Name, Age, Birthdate, and Birthplace.','error');
                return;
            }
        }

        $('#generateCertificate').prop('disabled', true);

        $.ajax({
            url: 'walkin_certificates.php',
            type: 'POST',
            data: { 
                action: 'generate_certificate', 
                resident_id: residentId, 
                template_id: templateId, 
                purpose: purpose,
                earnings_per_month: earnings,
                profession_occupation: profession,
                child_id: childId || null,
                child_fullname: childFullname,
                child_age: childAge,
                child_birthdate: childBirthdate,
                child_birthplace: childBirthplace,
                fullname: fullname,
                age: age,
                civil_status: civilStatus,
                address: address,
                birth_place: birthplace,
                birthdate: birthdate,
                sex: sex,
                voter_status: voterStatus
            },
            dataType: 'json',
            success: function(res){
                if(res.success){
                    showMessageModal('Certificate Generated!','success');
                    $('#resident_search,#resident_id,#resident_fullname,#resident_age,#resident_status,#resident_address,#resident_birthplace,#resident_birthdate,#resident_sex,#resident_voter_status,#resident_profession,#template_id,#purpose,#earnings_per_month,#child_id,#child_fullname,#child_age,#child_birthdate,#child_birthplace').val('');
                    $('#resident_card').addClass('hidden');
                    $('#child_card').hide();
                    $.ajax({
                        url: 'fetch_recent_walkin.php',
                        type: 'GET',
                        success: function(html){
                            $('table tbody').html(html);
                        }
                    });
                } else {
                    showMessageModal(res.message || 'Failed to generate certificate.','error');
                }
            },
            error: function(){
                showMessageModal('An error occurred. Please try again.','error');
            },
            complete: function(){
                $('#generateCertificate').prop('disabled', false);
            }
        });
    });

    $('#child_search').on('input', function() {
        let query = $(this).val().trim();
        if(query.length === 0){ $('#child_dropdown').empty().hide(); return; }
        $.ajax({
            url: 'walkin_certificates.php',
            type: 'GET',
            data: { child_search: query },
            dataType: 'json',
            success: function(data) {
                let html = '';
                if(data.length > 0){
                    data.forEach(child => {
                        html += `<div class="p-2 hover:bg-green-100 cursor-pointer" 
                                    data-id="${child.child_id}" 
                                    data-fullname="${child.first_name} ${child.last_name}"
                                    data-age="${child.age}" 
                                    data-birthplace="${child.birth_place}" 
                                    data-birthdate="${child.birthdate}"
                                    data-address="${child.resident_address}">
                                    ${child.first_name} ${child.last_name}
                                 </div>`;
                    });
                    $('#child_dropdown').html(html).show();
                } else {
                    $('#child_dropdown').html('<div class="p-2 text-gray-500">No results found</div>').show();
                }
            }
        });
    });

    $(document).on('click', '#child_dropdown div', function() {
        let id = $(this).data('id');
        let fullname = $(this).data('fullname');
        let age = $(this).data('age');
        let birthplace = $(this).data('birthplace');
        let birthdate = $(this).data('birthdate');
        let address = $(this).data('address');

        $('#child_id').val(id);
        $('#child_fullname').val(fullname);
        $('#child_age').val(age);
        $('#child_birthdate').val(birthdate);
        $('#child_birthplace').val(birthplace);

        $('#child_name').text(fullname);
        $('#child_age_status').text(age + ' years old');
        $('#child_birthdate_card').text(birthdate);
        $('#child_birthplace_card').text(birthplace);

        $('#child_card').show();
        $('#child_manual').show();
        $('#child_dropdown').hide();
    });

    $(document).click(function(event) {
        if(!$(event.target).closest('#child_search, #child_dropdown').length){
            $('#child_dropdown').hide();
        }
    });

    function showMessageModal(message, type='success'){
        let icon = type === 'success' ? 'check_circle' : 'error';
        let color = type === 'success' ? 'text-green-500' : 'text-red-500';
        $('#modalIcon').text(icon).removeClass('text-green-500 text-red-500').addClass(color);
        $('#modalMessage').text(message);
        $('#messageModal').removeClass('hidden').addClass('flex');
    }

    $('#closeMessageModal, #okMessageBtn').click(function(){
        $('#messageModal').removeClass('flex').addClass('hidden');
    });

});

$('#earnings_per_month').on('input', function() {
    let maxLength = 10;
    let value = $(this).val();
    if(value.length > maxLength){
        $(this).val(value.slice(0, maxLength));
    }
});
