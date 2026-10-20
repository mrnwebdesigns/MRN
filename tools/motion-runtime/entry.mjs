// Dedicated namespace: never replace an extension's complete window.Motion API.
import {animate, inView, scroll} from 'motion';
window.mrnBaseStackCoreMotion = {animate, inView, scroll};
