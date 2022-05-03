import {
	reactive,
	computed,
	toRefs,
	nextTick,
} from 'vue';

export default function useFormSubmission({ wrapper, skipFalsy, setErrors }) {
	const data = reactive({
		isSubmitting: null,
		response: null,
		errorMessage: null,
		fieldErrors: null,
	});

	const firstErrorElement = computed(() => {
		if (!data.fieldErrors) return null;

		const firstError = Object.keys(data.fieldErrors)[0];
		const target = wrapper.value.querySelector(`input[name=${firstError}]`);

		return target;
	});

	const thankYouElement = computed(() => {
		if (!data.response) return null;

		const target = wrapper.value.querySelector('.e-thank-you');

		return target;
	});

	const csrfToken = () => {
		const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

		if (!token) {
			console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
		}

		return token;
	};

	const scrollTo = async (success) => {
		await nextTick();

		const el = success
			? thankYouElement.value
			: firstErrorElement.value;

		el.scrollIntoView({
			behavior: 'smooth',
			block: 'center',
			inline: 'center',
		});
	};

	const onSubmitSuccess = async (response) => {
		const { redirect, response: resp } = await response.json();

		if (redirect) {
			window.location = redirect;
		} else if (resp) {
			data.response = resp;
		}

		scrollTo(true);
	};

	const onSubmitFailure = async (response) => {
		if (response.status !== 422) {
			throw new Error(`Unexpected response status: ${response.status}`);
		}

		const { errors = {}, message } = await response.json();

		data.errorMessage = message;
		data.fieldErrors = errors;

		setErrors(errors);
		scrollTo(false);
	};

	const submit = async (action, values) => {
		data.isSubmitting = true;

		const body = new FormData();

		Object.keys(values)
			.filter((key) => values[key] || !skipFalsy)
			.forEach((key) => body.append(key, values[key]));

		try {
			const response = await fetch(action, {
				method: 'POST',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'X-CSRF-TOKEN': csrfToken(),
				},
				body,
			});

			if (!response.ok) {
				throw response;
			}

			onSubmitSuccess(response);
		} catch (error) {
			onSubmitFailure(error);
		} finally {
			data.isSubmitting = null;
		}
	};

	return {
		submit,
		...toRefs(data),
	};
}
