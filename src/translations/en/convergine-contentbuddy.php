<?php return [
	/* Settings -> navigation */
	'Settings'=>'Settings',
	'Fields'=>'Fields',
	'API'=>'API',
	'Content Generator'=>'Content Generator',
	'Prompts Templates'=>'Prompts Templates',
	'Image Generation' => 'Image Generation',

    /* Settings -> General Page */
    'OpenAI Preferred Model' => 'OpenAI Preferred model',
    'modelDescription' => 'Some models are more capable than others. For example, the davinci model is more capable than the ada model, which is more capable than the babbage model, and so on. The davinci model is the most capable model, but it is also the most expensive one. The ada model is the least capable model, but it is also the cheapest. For more information, see https://platform.openai.com/docs/models',
    'Language for Text Generation' => 'Language for Text Generation',
    'languageDescription' => 'The language of the text you want to generate. For consistent results, make sure that the text you write in your post is written in the same language you picked here.',
    'Image Generation Styles' => 'Image Generation Styles',
    'imageGenDescription' => 'Image styles are phrases that will be added at the end an image\'s prompt to change the look of the image. You can use it to maintain a certain style of images for your posts. If you like colorful images, you can add "colourful" as one of the styles. If you want to appear as if they were drawn by leonardo davinci, add "by leonardo davinci" as a style. Each line would be considered a different style and Content Buddy will choose a random style out of this list each time it generates an image and append it at the end of the prompt.',
    'Default Image Size' => 'Default Image Size',
    'imageDescription' => 'The size of the images you want to generate with DALL-E for the generated content. The larger the image, the more expensive it is.',
	'systemMessageDescription' => 'If anything is added in this textarea - then ALL prompts sent to API will include it at the end of the prompt:',
	'imageModelDescription' => 'Select the default image generation API.',
    'dalleModelDescription' => 'Select the default model that will be used when generating an image using OpenAI. For more information, see https://platform.openai.com/docs/models/dall-e',

	/* Settings -> Api Page */
	'API: Access Token'=>'API: Access Token',
	'Access token for the ChatGPT API'=>'Access token for the ChatGPT API',
	'API: Max Tokens'=>'API: Max Tokens',
	'maxAmountTokensDescription'=>'Maximum amount of tokens for chatgpt\'s response (<a href="https://platform.openai.com/docs/introduction/tokens">read about tokens</a>)',
	'Temperature'=>'Temperature',
	'temperatureDescription'=>'What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic',
	'Frequency Penalty'=>'Frequency Penalty',
	'frequencyPenaltyDescription'=>'Number between -2.0 and 2.0. Positive values penalize new tokens based on whether they appear in the text so far, increasing the model\'s likelihood to talk about new topics.',
	'Presence Penalty'=>'Presence Penalty',
	'presencePenaltyDescription'=>'Number between -2.0 and 2.0. Positive values penalize new tokens based on their existing frequency in the text so far, decreasing the model\'s likelihood to repeat the same line verbatim',

	/* Settings -> Fields Page */
	'Show Prompts Menu'=>'Show Quick Menu',
	'No fields exist yet.'=>'No fields exist yet.',
    'fieldsDescriptionText'=>'In this section, you have the ability to choose which fields in your entries will show the "Content Buddy" dropdown menu.',

	/* Settings -> stability options */
	'Please enter your StabilityAI API key here. You can get your key from the <a href="https://platform.stability.ai/account/keys" target="_blank">StabilityAI website</a>.\nStabilityAI generated images are much better quality but require separate account and credits.\nNew accounts get 25 image generation credits upon registration.' =>'Please enter your StabilityAI API key here. You can get your key from the  <a href="https://platform.stability.ai/account/keys" target="_blank">StabilityAI website</a>.\nStabilityAI generated images are much better quality but require separate account and credits.\nNew accounts get 25 image generation credits upon registration.',
	'stabilityEngineDescription' =>'The default model that will be used when generating an image using Stability.ai.',
	'stabilitySamplerDescription' => 'A sampler determines how the image is "calculated". A sampler processes an input (prompt) to produce an output (image). Since these samplers are different mathematically, they will produce difference results for the same prompt.',
	'stabilityStepsDescription' =>'Generation steps control how many times the image is sampled. Increasing the number of steps might give you better results, up to a point where there\'re diminishing returns. More steps would also cost you more.',
	'stabilityCFGDescription' => 'Prompt strength (CFG scale) controls how much the final image will adhere to your prompt. Lower values would give the model more "creativity", while higher values will produce a final image that\'s close to your prompt.',
	'Enable Generate Image From Text' => 'Enable Generate Image From Text',

	/* Prompts Page */
	'Edit Prompt'=>'Edit Prompt',
	'New Prompt'=>'New Prompt',
	'Prompt Template'=>'Prompt Template',
	'Prompts'=>'Prompts',
	'Words Count'=>'Words Count',
	'Enabled'=>'Enabled',
	'Replace the highlighted text'=>'Replace the highlighted text',
	'promptReplaceText'=>'The selected text will be automatically sent to ChatGPT and the response will be appended immediately after the selected text. You can modify this behavior for each prompt separately by selecting this option.',
	'Number of words to generate'=>'Number of words to generate',
	'Number of words'=>'Number of words',
	'numberOfWordsDescription'=>'Choose this option if you want to generate a fixed number of words, regardless of how long the selected text is. This is helpful for certain types of prompts, like generating a paragraph on a certain topic for example.',
	'Relative to length of text selected'=>'Relative to length of text selected',
	'Multiplier'=>'Multiplier',
	'multiplierDescription'=>'Choose this option if you want to calculate the length of the generated words relative to the length of words selected. 1x = same length as select text, 2x means two times, etc. Summarization is a good candidate to use this option for.',
	'Prompt Template'=>'Prompt Template',
	'No prompts exist yet.'=>'No prompts exist yet.',
	'Prompt updated'=>'Prompt updated',
	'Prompt removed'=>'Prompt removed',
	'Using the locale '=>'Using the locale ',
	'Translate to {language}'=>'Translate to {language}',
	'API Access Token required.'=>'API Access Token required.',
	'Need to setup API: Access Token'=>'ChatGPT API Access Token missing. Please add it in plugin settings under \'API Settings\' tab.',
	'Content Buddy fields are not selected in settings. Please select fields in plugin settings under \'Fields Settings\' tab.'=>'Content Buddy fields are not selected in settings. Please select fields in plugin settings under \'Fields Settings\' tab.',
	'The reply has exceeded the specified maximum length. To fix this, either increase the value of the max_token setting or try telling chat-gpt to limit itself to a certain number of words.'=>'The reply has exceeded the specified maximum length. To fix this, either increase the value of the max_token setting or try telling chat-gpt to limit itself to a certain number of words.',
	'No prompts found'=>'No prompts found',
	'Translate to {language}'=>'Translate to {language}',
	'Words count must be more than 5'=>'Words count must be more than 5',
	'To avoid time-out errors, we strongly recommend changing your server <strong>max_execution_time</strong> to {preferred_time} (Current value: {current_time} seconds)'=>'To avoid time-out errors, we strongly recommend changing your server <strong>max_execution_time</strong> to {preferred_time} (Current value: {current_time} seconds)',
    'contentWelcome' => 'Content Buddy is a drafting aid tool that facilitates quick draft writing. Please remember to review and edit your drafts before publishing to achieve the best results, as Content Buddy is not a substitute for human editing. Fill out the form below, select the desired number of articles to generate, submit, and enjoy the process!',

	/* Content Generator Page */
	'Select Section'=>'Select Section',
	'Select Field'=>'Select Field',
	'Please enter a brief description of the topic you want to write about.'=>'Please enter a brief description of the topic you want to write about.',
	'Topic'=>'Topic',
	'SEO Keywords'=>'SEO Keywords',
	'SEO keywords to focus on (comma-separated)'=>'SEO keywords to focus on (comma-separated)',
	'Number of Articles:'=>'Number of Articles:',
	'Sections per article:'=>'Sections per article:',
	'Maximum words per section:'=>'Maximum words per section:',
	'Create article(s) in:'=>'Create article(s) in:',
	'Section / Type'=>'Section / Type',
	'Description Field'=>'Description Field',
	'Generate outline'=>'Generate outline',
	'Generate featured entry image'=>'Generate featured entry image',
	'Generate conclusion'=>'Generate conclusion',
	'Generate TL;DR'=>'Generate TL;DR',
	'Generate section images'=>'Generate section images',
	'Section Images Volume (Folder)'=>'Section Images Volume (Folder)',
	'You did not set up any volumes/filesystems yet.'=>'You did not set up any volumes/filesystems yet.',
	'You did not set up any featured image fields for the selected section.'=>'You did not set up any featured image fields for the selected section.',
	'Featured Image Field'=>'Featured Image Field',
	'promptsCustomize' => 'Customize Prompts',
	'promptsCustomizeDescription' => 'You can fine-tune the prompts that Content Buddy uses to generate content here. Changes to the prompts below will not be saved, and will be used only for current content generation request.',
	'Generate'=>'Generate',
	'You can use the following placeholders in your prompts:'=>'You can use the following placeholders in your prompts:',
	'this will be replaced with your article description/topic.'=>'this will be replaced with your article description/topic.',
	'this will be replaced with the text of generated section.'=>'this will be replaced with the text of generated section.',
	'this will be replaced with text needed for that prompt.'=>'this will be replaced with text needed for that prompt.',
	'this will be replaced with the suggested headlines for the sections about to be generated.'=>'this will be replaced with the suggested headlines for the sections about to be generated.',
	'this will be replaced with the SEO keywords you entered.'=>'this will be replaced with the SEO keywords you entered.',
	'this will be replaced with the number of headlines for the article section.'=>'this will be replaced with the number of headlines for the article section.',
    'Fixed number of words'=>'Fixed number of words',

	/* Site Translation */
	'Site Translation'=>'Site Translation',
	'Translate?'=>'Translate?',
	'Generate Translations'=>'Generate Translations',
	'Additional instructions'=>'Additional instructions',
	'additionalInstructionsDescription'=>'You can provide additional instructions to ChatGPT to fine-tune the translation prompt. Things like \'do not translate links\' or translate and rephrase in specific tone or style, etc.',
	'Overwrite Existing Translations'=>'Overwrite Existing Translations',
	'overwriteExistingTranslationsDescription'=>'If selected, all existing translations that will be found during translation process will be overwritten.',
	'Are you sure to remove record and associated logs?'=>'Are you sure to remove record and associated logs?',
	'translationStarted'=>'Translations request added',
	'selectSectionInstructions'=>'Select the section that will be translated',
	'Select Language'=>'Select Language',
	'selectLanguageInstructions'=>'Select the language you would like to translate the selected section. You can select only the languages that are set up in Settings ➝ Sites',
	'Translations Log'=>'Translations Log',
	'translateFieldsDescriptionText'=>'Select the fields that will be translated in the selected section.',
	'Translate Matrix Fields'=>'Translate Matrix Fields',
	'translateMatrixFieldsText'=>'Translate all Matrix fields content, recursively',


	/* General error messages*/
	'badGatewayError' => 'The server returned "502 Bad Gateway" error. This could mean that there is an issue with OpenAI server connection. Please try again a bit later.',
	'tooManyRequestsError' => 'OpenAI service returned "429 Too Many Requests" error. The model is currently overloaded with other requests. Pleases try again later.',
	'badRequestError' => 'Your request was rejected as a result of ChatGPT\'s internal safety system. Please adjust your input and try again.',
	'unauthorizedError' => 'Incorrect API key provided',
	'selectPromptText'=>'Please add new or select existing text to use this prompt.',
	'Prompt not found'=>'Prompt not found',
	'Missing required params'=>'Missing required params',

	'licenseNotice'=>'By maintaining a valid license for our plugin on live domains, you\'re directly supporting our team\'s efforts. This not only champions the broader CraftCMS community but also enables us to continually innovate and deliver enhanced features for you.',
	'entryTranslationStarted'=>'Translations request added to queue.',
];
