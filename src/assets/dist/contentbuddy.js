class BuddyGenerateClass {

	$topic;
	$sections;
	$max_words;
	$entry_section;
	$entry_field;
	$folder_id;
	$featured_image_id
	$form;
	errors = [];
	$error_alert = $("#buddy-alert-error");
	$success_alert = $("#buddy-alert-success");
	fields_select_entry_field_options;
	fields_select_featured_field_options;
	$entry_section_select = $("#buddy_entry_section");
	$spoiler_label = $(".buddy-spoiler label:first-child");
	$section_images = $("#buddy_include_section_images");
	$folder_id_cont = $("#buddy_folder_id_cont");

	$featured_image = $("#buddy_include_featured_image");
	$featured_field_id_cont = $("#buddy_featured_field_id_cont");



	constructor(form_id) {
		this.$form = $(form_id);
		this.$topic = $("#buddy_topic");

		this.$sections = $("#buddy_sections");
		this.$max_words = $("#buddy_max_words");

		this.$entry_section = $("#buddy_entry_section");
		this.$entry_field = $("#buddy_entry_field");
		this.$folder_id = $("#buddy_folder_id");
		this.$featured_image_id = $("#buddy_featured_field_id");

		this.$form.on('submit',e => this.generate_entry(e))
		this.init_fields_select()
		this.$entry_section_select.on('change',e => this.handle_entry_section_select(e))
		this.$spoiler_label.on('click',e => this.handle_spoiler_click(e))
		this.$section_images.on('change',e => this.handle_section_image_click(e))
		this.$featured_image.on('change',e => this.handle_featured_image_click(e))
		$('input[type="range"]').on('mousedown mousemove mouseup keydown',function (){
			let prefix = $(this).data('prefix')??'';
			let cont = $(this).parents('.buddy-slider-cont').find('span')
			cont.text(prefix + $(this).val())
		})
	}
	handle_section_image_click(e){
		//alert('ff')
		if($(e.currentTarget).is(':checked')){
			this.$folder_id_cont.show()
		}else{
			this.$folder_id_cont.hide()
		}
	}

	handle_featured_image_click(e){
		if($(e.currentTarget).is(':checked')){
			this.$featured_field_id_cont.show()
		}else{
			this.$featured_field_id_cont.hide()
		}
	}


	handle_spoiler_click(e){
		const $spoiler = $(e.currentTarget).parent();
		if($spoiler.hasClass('active')){
			$spoiler.removeClass('active')
		}else{
			$spoiler.addClass('active')
		}
	}

	init_fields_select(){
		this.fields_select_entry_field_options = $('#buddy_entry_field option');
		console.log(this.fields_select_entry_field_options)
		$('#buddy_entry_field').html(this.fields_select_entry_field_options[0])

		this.fields_select_featured_field_options = $('#buddy_featured_field_id option');
		console.log(this.fields_select_featured_field_options)
		$('#buddy_featured_field_id').html(this.fields_select_featured_field_options[0])
		
	}

	handle_entry_section_select(e){

		const val = $(e.currentTarget).val()
		const entry_field_values = this.fields_select_entry_field_options.clone();
		$('#buddy_entry_field').html(entry_field_values.filter(function () {
			return $(this).data("id") == val;
		}))

		const entry_featured_image_values = this.fields_select_featured_field_options.clone();
		$('#buddy_featured_field_id').html(entry_featured_image_values.filter(function () {
			return $(this).data("id") == val;
		}))
		if(this.$featured_image_id.find('option').length){
			this.$featured_field_id_cont.find('.error').hide()
			this.$featured_field_id_cont.find('.buddy-select').show()
		}else{
			this.$featured_field_id_cont.find('.error').show()
			this.$featured_field_id_cont.find('.buddy-select').hide()
		}
	}
	generate_entry (e){
		console.log('event',e)
		e.preventDefault();
		if(this.validate_form()){
			//let formData = this.$form.serializeArray();
			$("#buddy_generate_entry").prop('disabled',true).addClass('loading')
			$.ajax({
				type: "POST",
				url: "/",
				data: this.$form.serializeArray(),
				success: data =>{
					if(data.res){
						this.view_success_message(data.msg)
					}else{
						this.errors.push(data.msg);
						this.view_error_message()
					}
					$("#buddy_generate_entry").prop('disabled',false).removeClass('loading')
				},
				error:e => {
					console.log(e)
					this.errors.push(e.statusText);
					this.view_error_message()
					$("#buddy_generate_entry").prop('disabled',false).removeClass('loading')
				},
				dataType:'json'
			});
		}

	}

	validate_form (){
		this.hide_message()

		if(this.$topic.val()==''){
			this.errors.push('Please, provide topic');
			console.log('Please, provide topic')
		}
		if(this.$sections.val()==''){
			this.errors.push('Please, type sections count per article');

		}
		if(this.$max_words.val()==''){
			this.errors.push('Please, type maximum words per section');

		}
		if(this.$entry_section.val()==''){
			this.errors.push('Please, select entry section');

		}
		if(this.$entry_field.val()==''){
			this.errors.push('Please, select entry description field');

		}
		if(this.$section_images.is(":checked") && !this.$folder_id.val()){
			this.errors.push('Please, select volume for images');
		}
		if(this.$featured_image.is(":checked") && this.$featured_image_id.val()==''){
			this.errors.push('Please, select featured image field');
		}
		if(this.errors.length){
			this.view_error_message()
			return false
		}

		return true;
	}

	view_error_message(){
		let message = ""
		this.errors.forEach( el => {message+=`<li>${el}</li>`})
		this.$error_alert.html(message).show();
	}

	view_success_message(message){
		this.$success_alert.html(message).show();
	}

	hide_message(){
		this.errors = [];
		this.$error_alert.html('').hide();
		this.$success_alert.html('').hide();
	}
}

document.addEventListener("DOMContentLoaded", () => {
	orderPaymentsClass = new BuddyGenerateClass("#buddy_generate_form")
});