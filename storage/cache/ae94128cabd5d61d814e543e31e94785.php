

<?php $__env->startSection('title', 'Bond Application | Manage'); ?>

<?php $__env->startSection('content'); ?>
<section id="hero">
    <div class="container flex flex-col-reverse md:flex-row items-center px-6 mx-auto mt-10">
        
        <div class="flex flex-col mb-32 space-y-12 md:w-1/2">
            <h1 class="text-4xl font-bold text-center md:text-5xl md:text-left">Bond Application</h1>
            <p class="text-sky-900">Complete the form below and one of our agents will be in touch shortly.</p>
        </div>
        
        <div class="md:w-1/2" style="margin-left: 3rem;">
            <img src="/img/pic_candle_chart-edit.png" alt="Application" class="w-full h-auto">
        </div>

    </div>
</section>

<section id="form" class="py-12">
    <div class="container flex flex-col px-6 mx-auto md:flex-row items-center justify-between max-w-6xl md:gap-16">
        
        <div class="w-full md:w-1/2 pb-12 md:pb-0">
            <img src="/img/pic_piechart_edit.png" alt="Process" class="w-full h-auto mx-auto max-w-md md:max-w-full">
        </div>
        
        <div class="w-full md:w-1/2">
            <form action="/apply" method="POST" class="w-full max-w-lg mx-auto bg-slate-50 p-6 md:p-8 rounded-2xl"
                  x-data="applyFormValidation()"
                  @submit.prevent="submitForm($el)">
                
                <div class="flex flex-wrap -mx-3 mb-6">
                    <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                        <label class="block uppercase text-gray-700 text-xs font-bold mb-2 tracking-wider">First Name*</label>
                        <input class="w-full bg-gray-200 border rounded-xl py-3 px-4 focus:outline-none focus:bg-white transition duration-200" 
                               :class="errors.first_name ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'"
                               name="first_name" 
                               type="text" 
                               x-model="fields.first_name" 
                               @blur="validateField('first_name')">
                        <p class="text-red-600 text-xs mt-1 font-semibold" x-show="errors.first_name" x-text="errors.first_name"></p>
                    </div>
                    
                    <div class="w-full md:w-1/2 px-3">
                        <label class="block uppercase text-gray-700 text-xs font-bold mb-2 tracking-wider">Last Name*</label>
                        <input class="w-full bg-gray-200 border rounded-xl py-3 px-4 focus:outline-none focus:bg-white transition duration-200" 
                               :class="errors.last_name ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'"
                               name="last_name" 
                               type="text" 
                               x-model="fields.last_name" 
                               @blur="validateField('last_name')">
                        <p class="text-red-600 text-xs mt-1 font-semibold" x-show="errors.last_name" x-text="errors.last_name"></p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block uppercase text-gray-700 text-xs font-bold mb-2 tracking-wider">Phone Number*</label>
                    <input class="w-full bg-gray-200 border rounded-xl py-3 px-4 focus:outline-none focus:bg-white transition duration-200" 
                           :class="errors.phone ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'"
                           name="phone" 
                           type="tel" 
                           placeholder="012 345 6789" 
                           maxlength="10"
                           x-model="fields.phone" 
                           @blur="validateField('phone')">
                    <p class="text-red-600 text-xs mt-1 font-semibold" x-show="errors.phone" x-text="errors.phone"></p>
                </div>

                <div class="mb-6">
                    <label class="block uppercase text-gray-700 text-xs font-bold mb-2 tracking-wider">Email Address*</label>
                    <input class="w-full bg-gray-200 border rounded-xl py-3 px-4 focus:outline-none focus:bg-white transition duration-200" 
                           :class="errors.email ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'"
                           name="email" 
                           type="email" 
                           x-model="fields.email" 
                           @blur="validateField('email')">
                    <p class="text-red-600 text-xs mt-1 font-semibold" x-show="errors.email" x-text="errors.email"></p>
                </div>

                <div class="mb-8">
                    <label class="block uppercase text-gray-700 text-xs font-bold mb-2 tracking-wider">Bond Amount*</label>
                    <input class="w-full bg-gray-200 border rounded-xl py-3 px-4 focus:outline-none focus:bg-white transition duration-200" 
                           :class="errors.bond_amount ? 'border-red-500 ring-1 ring-red-500' : 'border-gray-200'"
                           name="bond_amount" 
                           type="text" 
                           x-model="fields.bond_amount" 
                           @blur="validateField('bond_amount')">
                    <p class="text-red-600 text-xs mt-1 font-semibold" x-show="errors.bond_amount" x-text="errors.bond_amount"></p>
                </div>

                <button style="display: inline-block; margin-top: 24px;" 
                        class="bg-red-600 hover:bg-red-500 text-white font-bold py-2 px-12 rounded-full transition duration-300" 
                        type="submit">
                    Submit Application
                </button>
            </form>
        </div>
    </div>
</section>

<script>
function applyFormValidation() {
    return {
        // Form properties mapped via x-model
        fields: {
            first_name: '',
            last_name: '',
            phone: '',
            email: '',
            bond_amount: ''
        },
        // Error string fields
        errors: {
            first_name: '',
            last_name: '',
            phone: '',
            email: '',
            bond_amount: ''
        },

        /**
         * Real-time field validation matching your historical jQuery rulesets.
         */
        validateField(name) {
            let value = this.fields[name].trim();
            this.errors[name] = '';

            // Required field assertion
            if (!value) {
                if (name === 'first_name') this.errors[name] = "Please enter your first name";
                if (name === 'last_name') this.errors[name] = "Please enter your last name";
                if (name === 'phone') this.errors[name] = "Please enter your phone number";
                if (name === 'email') this.errors[name] = "Please enter your email";
                if (name === 'bond_amount') this.errors[name] = "Please enter your bond amount";
                return false;
            }

            // Minlength constraints on names
            if ((name === 'first_name' || name === 'last_name') && value.length < 2) {
                this.errors[name] = "Name at least 2 characters";
            }

            // Strictly 10 digits check for South African cellular tracking formats
            if (name === 'phone') {
                if (!/^\d+$/.test(value)) {
                    this.errors[name] = "Enter numbers only please";
                } else if (value.length !== 10) {
                    this.errors[name] = "Please enter a 10 digit number";
                }
            }

            // Standard RFC Email pattern matching
            if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                this.errors[name] = "Not a valid email address";
            }

            // Digit and length requirements for major capital credit calculation queries
            if (name === 'bond_amount') {
                if (!/^\d+$/.test(value)) {
                    this.errors[name] = "Please enter numbers only";
                } else if (value.length < 6) {
                    this.errors[name] = "Please enter at least six figures";
                }
            }

            return this.errors[name] === '';
        },

        /**
         * Intercept standard submission arrays and check state boundaries natively.
         */
        submitForm(formElement) {
            let isValid = true;
            
            Object.keys(this.fields).forEach(key => {
                if (!this.validateField(key)) {
                    isValid = false;
                }
            });

            if (isValid) {
                formElement.submit(); // Dispatches native execution payload to backend
            }
        }
    }
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\slim-killer\resources\views/apply.blade.php ENDPATH**/ ?>